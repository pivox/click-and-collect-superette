<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\OrderStatus;
use App\Message\SendPickupReminderMessage;
use App\Repository\OrderRepository;
use App\Repository\PickupSessionRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class SendPickupReminderMessageHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private PickupSessionRepository $pickupSessionRepository,
        private NotificationService $notificationService,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SendPickupReminderMessage $message): void
    {
        if (!Uuid::isValid($message->orderId)) {
            return;
        }

        $order = $this->orderRepository->find($message->orderId);
        if (null === $order) {
            return;
        }

        if (OrderStatus::Ready !== $order->getStatus()) {
            return;
        }

        $pickupSlot = $order->getPickupSlot();
        $now = $this->clock->now();
        if (null === $pickupSlot || $now >= $pickupSlot->getStartsAt()) {
            return;
        }

        if ($now < $pickupSlot->getStartsAt()->modify('-1 hour')) {
            return;
        }

        $pickupSession = $this->pickupSessionRepository->findOneByOrder($order);
        // scannedAt guard is intentionally kept: the OrderStatus::Ready check above should already
        // prevent this, but a scan transitions the order to pickup_pending asynchronously and a
        // delayed message could be delivered in the window between scan and status update.
        if (null === $pickupSession || $pickupSession->isUsed() || null !== $pickupSession->getScannedAt()) {
            return;
        }

        $this->notificationService->notifyCustomerPickupReminder($order);
        $this->entityManager->flush();
    }
}
