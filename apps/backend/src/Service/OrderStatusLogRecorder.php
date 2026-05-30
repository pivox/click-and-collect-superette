<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderStatusLog;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OrderStatusLogRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.order')]
        private LoggerInterface $logger,
    ) {
    }

    public function record(Order $order, OrderStatus $status, ?string $note = null): OrderStatusLog
    {
        $orderId = $order->getId()->toRfc4122();
        $fromStatus = $this->resolveOriginalStatus($order);

        $this->logger->debug('order.status_change.start', [
            'order_id' => $orderId,
            'from_status' => $fromStatus?->value,
            'to_status' => $status->value,
        ]);

        $log = new OrderStatusLog($order, $status, $note);
        $this->entityManager->persist($log);
        $this->logger->info('order.status_changed', [
            'order_id' => $orderId,
            'store_id' => $order->getShop()->getId()->toRfc4122(),
            'from_status' => $fromStatus?->value,
            'to_status' => $status->value,
        ]);

        return $log;
    }

    private function resolveOriginalStatus(Order $order): ?OrderStatus
    {
        $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($order);
        $status = $originalData['status'] ?? null;

        return $status instanceof OrderStatus ? $status : null;
    }
}
