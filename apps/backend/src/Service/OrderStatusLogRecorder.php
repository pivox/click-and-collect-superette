<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderStatusLog;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class OrderStatusLogRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function record(Order $order, OrderStatus $status, ?string $note = null): OrderStatusLog
    {
        $log = new OrderStatusLog($order, $status, $note);
        $this->entityManager->persist($log);
        $this->logger->info('order.status_changed', [
            'order_id' => $order->getId()->toRfc4122(),
            'store_id' => $order->getShop()->getId()->toRfc4122(),
            'from_status' => $this->resolveOriginalStatus($order)?->value,
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
