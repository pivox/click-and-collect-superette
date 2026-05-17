<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\OrderStatus;
use App\Message\PartialAcceptanceReminderMessage;
use App\Repository\OrderRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class PartialAcceptanceReminderMessageHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private NotificationService $notificationService,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private int $partialAcceptanceReminderLeadSeconds,
        private int $partialAcceptanceExpirationLeadSeconds,
    ) {
    }

    public function __invoke(PartialAcceptanceReminderMessage $message): void
    {
        if (!Uuid::isValid($message->orderId)) {
            return;
        }

        $this->entityManager->wrapInTransaction(function () use ($message): void {
            $order = $this->orderRepository->find($message->orderId);
            if (null === $order || OrderStatus::PartiallyAccepted !== $order->getStatus()) {
                return;
            }

            $pickupSlot = $order->getPickupSlot();
            $now = $this->clock->now();
            if (null === $pickupSlot || $now >= $pickupSlot->getStartsAt()) {
                return;
            }

            $remindsAt = $pickupSlot->getStartsAt()->modify('-'.$this->partialAcceptanceReminderLeadSeconds.' seconds');
            $expiresAt = $pickupSlot->getStartsAt()->modify('-'.$this->partialAcceptanceExpirationLeadSeconds.' seconds');
            if ($now < $remindsAt || $now >= $expiresAt) {
                return;
            }

            // cycleId is generated at dispatch time so each partial-acceptance cycle gets its own
            // idempotency key, even when the same pickup slot is reused across cycles.
            $cycleType = NotificationService::TYPE_PARTIAL_ACCEPTANCE_REMINDER.'_'.$message->cycleId;
            $this->notificationService->notifyCustomerPartialAcceptanceReminder($order, $cycleType);
            $this->entityManager->flush();
        });
    }
}
