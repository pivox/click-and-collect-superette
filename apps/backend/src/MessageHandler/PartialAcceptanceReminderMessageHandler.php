<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\OrderStatus;
use App\Message\PartialAcceptanceReminderMessage;
use App\Repository\OrderRepository;
use App\Service\NotificationService;
use App\Service\PickupSlotDisplayTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PartialAcceptanceReminderMessage $message): void
    {
        $this->logger->debug('messenger.received', [
            'message' => PartialAcceptanceReminderMessage::class,
            'order_id' => $message->orderId,
        ]);

        try {
            $this->handle($message);
            $this->logger->info('messenger.handled', [
                'message' => PartialAcceptanceReminderMessage::class,
                'order_id' => $message->orderId,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('messenger.failure', [
                'message' => PartialAcceptanceReminderMessage::class,
                'order_id' => $message->orderId,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function handle(PartialAcceptanceReminderMessage $message): void
    {
        if (!Uuid::isValid($message->orderId)) {
            $this->logger->warning('messenger.skipped', [
                'message' => PartialAcceptanceReminderMessage::class,
                'order_id' => $message->orderId,
                'reason' => 'invalid_uuid',
            ]);

            return;
        }

        $this->entityManager->wrapInTransaction(function () use ($message): void {
            $order = $this->orderRepository->find($message->orderId);
            if (null === $order || OrderStatus::PartiallyAccepted !== $order->getStatus()) {
                $this->logger->warning('messenger.skipped', [
                    'message' => PartialAcceptanceReminderMessage::class,
                    'order_id' => $message->orderId,
                    'reason' => null === $order ? 'order_not_found' : 'wrong_status',
                ]);

                return;
            }

            $pickupSlot = $order->getPickupSlot();
            $now = $this->clock->now();
            $slotStartsAt = null !== $pickupSlot ? PickupSlotDisplayTime::fromStoredLocalClock($pickupSlot->getStartsAt()) : null;
            if (null === $slotStartsAt || $now >= $slotStartsAt) {
                $this->logger->warning('messenger.skipped', [
                    'message' => PartialAcceptanceReminderMessage::class,
                    'order_id' => $message->orderId,
                    'reason' => null === $slotStartsAt ? 'no_pickup_slot' : 'slot_already_started',
                ]);

                return;
            }

            $remindsAt = $slotStartsAt->modify('-'.$this->partialAcceptanceReminderLeadSeconds.' seconds');
            $expiresAt = $slotStartsAt->modify('-'.$this->partialAcceptanceExpirationLeadSeconds.' seconds');
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
