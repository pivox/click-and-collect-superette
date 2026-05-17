<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Message\ExpireMerchantResponseMessage;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final readonly class MerchantResponseTimeoutScheduler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private ClockInterface $clock,
        private int $merchantResponseTimeoutLeadSeconds,
    ) {
    }

    public function scheduleForSubmittedOrder(Order $order): void
    {
        if (OrderStatus::Submitted !== $order->getStatus()) {
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

        $message = new ExpireMerchantResponseMessage($order->getId()->toRfc4122());
        $expiresAt = $slotStartsAt->modify('-'.$this->merchantResponseTimeoutLeadSeconds.' seconds');

        if ($now >= $expiresAt) {
            $this->messageBus->dispatch($message);

            return;
        }

        $delayMs = max(0, ($expiresAt->getTimestamp() - $now->getTimestamp()) * 1000);
        $this->messageBus->dispatch($message, [new DelayStamp($delayMs)]);
    }
}
