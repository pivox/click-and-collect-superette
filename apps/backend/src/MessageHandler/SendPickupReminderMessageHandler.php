<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\OrderStatus;
use App\Message\SendPickupReminderMessage;
use App\Repository\OrderRepository;
use App\Repository\PickupSessionRepository;
use App\Service\NotificationService;
use App\Service\PickupSlotDisplayTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendPickupReminderMessage $message): void
    {
        $this->logger->debug('messenger.received', [
            'message' => SendPickupReminderMessage::class,
            'order_id' => $message->orderId,
        ]);

        try {
            $this->handle($message);
            $this->logger->info('messenger.handled', [
                'message' => SendPickupReminderMessage::class,
                'order_id' => $message->orderId,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('messenger.failure', [
                'message' => SendPickupReminderMessage::class,
                'order_id' => $message->orderId,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function handle(SendPickupReminderMessage $message): void
    {
        if (!Uuid::isValid($message->orderId)) {
            $this->logger->warning('messenger.skipped', [
                'message' => SendPickupReminderMessage::class,
                'order_id' => $message->orderId,
                'reason' => 'invalid_uuid',
            ]);

            return;
        }

        $order = $this->orderRepository->find($message->orderId);
        if (null === $order) {
            $this->logger->warning('messenger.skipped', [
                'message' => SendPickupReminderMessage::class,
                'order_id' => $message->orderId,
                'reason' => 'order_not_found',
            ]);

            return;
        }

        if (OrderStatus::Ready !== $order->getStatus()) {
            $this->logger->warning('messenger.skipped', [
                'message' => SendPickupReminderMessage::class,
                'order_id' => $message->orderId,
                'reason' => 'wrong_status',
                'status' => $order->getStatus()->value,
            ]);

            return;
        }

        $pickupSlot = $order->getPickupSlot();
        $now = $this->clock->now();
        $slotStartsAt = null !== $pickupSlot ? PickupSlotDisplayTime::fromStoredLocalClock($pickupSlot->getStartsAt()) : null;
        if (null === $slotStartsAt || $now >= $slotStartsAt) {
            $this->logger->warning('messenger.skipped', [
                'message' => SendPickupReminderMessage::class,
                'order_id' => $message->orderId,
                'reason' => null === $slotStartsAt ? 'no_pickup_slot' : 'slot_already_started',
            ]);

            return;
        }

        if ($now < $slotStartsAt->modify('-1 hour')) {
            return;
        }

        $pickupSession = $this->pickupSessionRepository->findOneByOrder($order);
        // scannedAt guard is intentionally kept: the OrderStatus::Ready check above should already
        // prevent this, but a scan transitions the order to pickup_pending asynchronously and a
        // delayed message could be delivered in the window between scan and status update.
        if (null === $pickupSession || $pickupSession->isUsed() || null !== $pickupSession->getScannedAt()) {
            $this->logger->warning('messenger.skipped', [
                'message' => SendPickupReminderMessage::class,
                'order_id' => $message->orderId,
                'reason' => 'pickup_session_invalid',
            ]);

            return;
        }

        $this->notificationService->notifyCustomerPickupReminder($order);
        $this->entityManager->flush();
    }
}
