<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Message\ExpirePartialAcceptanceMessage;
use App\Message\PartialAcceptanceReminderMessage;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Uid\Uuid;

final readonly class PartialAcceptanceExpirationScheduler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private ClockInterface $clock,
        private int $partialAcceptanceReminderLeadSeconds,
        private int $partialAcceptanceExpirationLeadSeconds,
    ) {
    }

    public function scheduleForPartiallyAcceptedOrder(Order $order): void
    {
        if (OrderStatus::PartiallyAccepted !== $order->getStatus()) {
            return;
        }

        $pickupSlot = $order->getPickupSlot();
        if (null === $pickupSlot) {
            return;
        }

        $now = $this->clock->now();
        $slotStartsAt = $pickupSlot->getStartsAt();
        if ($now >= $slotStartsAt) {
            return;
        }

        $orderId = $order->getId()->toRfc4122();
        $remindsAt = $slotStartsAt->modify('-'.$this->partialAcceptanceReminderLeadSeconds.' seconds');
        $expiresAt = $slotStartsAt->modify('-'.$this->partialAcceptanceExpirationLeadSeconds.' seconds');

        if ($now < $expiresAt) {
            $cycleId = Uuid::v4()->toRfc4122();
            $this->dispatchAt(new PartialAcceptanceReminderMessage($orderId, $cycleId), $remindsAt, $now);
        }

        $this->dispatchAt(new ExpirePartialAcceptanceMessage($orderId), $expiresAt, $now);
    }

    private function dispatchAt(object $message, \DateTimeImmutable $targetTime, \DateTimeImmutable $now): void
    {
        if ($now >= $targetTime) {
            $this->messageBus->dispatch($message);

            return;
        }

        $delayMs = max(0, ($targetTime->getTimestamp() - $now->getTimestamp()) * 1000);
        $this->messageBus->dispatch($message, [new DelayStamp($delayMs)]);
    }
}
