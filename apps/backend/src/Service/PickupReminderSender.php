<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class PickupReminderSender
{
    private const WINDOW_MINUTES = 5;

    public function __construct(
        private OrderRepository $orderRepository,
        private PickupReminderNotifierInterface $notifier,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Sends pickup reminders for orders whose slot starts within 1h (±WINDOW_MINUTES).
     * Duplicate prevention is delegated to the notifier.
     *
     * @return int number of eligible orders processed
     */
    public function sendDueReminders(): int
    {
        $now = $this->clock->now();
        $from = $now->modify(\sprintf('+%d minutes', 60 - self::WINDOW_MINUTES));
        $to = $now->modify(\sprintf('+%d minutes', 60 + self::WINDOW_MINUTES));

        $orders = $this->orderRepository->findOrdersNeedingPickupReminder($from, $to, self::eligibleStatuses());

        foreach ($orders as $order) {
            $this->notifier->notifyCustomerPickupReminder($order);
        }

        if ([] !== $orders) {
            $this->entityManager->flush();
        }

        return \count($orders);
    }

    /**
     * @return list<OrderStatus>
     */
    public static function eligibleStatuses(): array
    {
        return [OrderStatus::Ready];
    }
}
