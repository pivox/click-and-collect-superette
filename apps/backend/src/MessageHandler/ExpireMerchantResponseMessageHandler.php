<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\OrderStatus;
use App\Message\ExpireMerchantResponseMessage;
use App\Repository\OrderRepository;
use App\Service\OrderTransitionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ExpireMerchantResponseMessageHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private OrderTransitionService $orderTransitionService,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private int $merchantResponseTimeoutLeadSeconds,
    ) {
    }

    public function __invoke(ExpireMerchantResponseMessage $message): void
    {
        if (!Uuid::isValid($message->orderId)) {
            return;
        }

        $this->entityManager->wrapInTransaction(function () use ($message): void {
            $order = $this->orderRepository->find($message->orderId);
            if (null === $order || OrderStatus::Submitted !== $order->getStatus()) {
                return;
            }

            $pickupSlot = $order->getPickupSlot();
            $now = $this->clock->now();
            if (null === $pickupSlot || $now >= $pickupSlot->getStartsAt()) {
                return;
            }

            $expiresAt = $pickupSlot->getStartsAt()->modify('-'.$this->merchantResponseTimeoutLeadSeconds.' seconds');
            if ($now < $expiresAt) {
                return;
            }

            $this->orderTransitionService->autoCancelMerchantResponseTimeout($order);
            $this->entityManager->flush();
        });
    }
}
