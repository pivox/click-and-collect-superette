<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Message\SendPickupReminderMessage;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final readonly class PickupReminderScheduler
{
    private const REMINDER_DELAY_SECONDS = 3600;

    public function __construct(
        private MessageBusInterface $messageBus,
        private ClockInterface $clock,
    ) {
    }

    public function scheduleForReadyOrder(Order $order): void
    {
        if (OrderStatus::Ready !== $order->getStatus()) {
            return;
        }

        $pickupSlot = $order->getPickupSlot();
        if (null === $pickupSlot) {
            return;
        }

        $now = $this->clock->now();
        $slotStartsAt = PickupSlotDisplayTime::fromStoredLocalClock($pickupSlot->getStartsAt());
        if ($now >= $slotStartsAt) {
            return;
        }

        $message = new SendPickupReminderMessage($order->getId()->toRfc4122());
        $reminderAt = $slotStartsAt->modify('-'.self::REMINDER_DELAY_SECONDS.' seconds');

        if ($now >= $reminderAt) {
            $this->messageBus->dispatch($message);

            return;
        }

        $delayMs = max(0, ($reminderAt->getTimestamp() - $now->getTimestamp()) * 1000);
        $this->messageBus->dispatch($message, [new DelayStamp($delayMs)]);
    }
}
