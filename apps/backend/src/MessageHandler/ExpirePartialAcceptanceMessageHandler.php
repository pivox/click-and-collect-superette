<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\OrderStatus;
use App\Message\ExpirePartialAcceptanceMessage;
use App\Repository\OrderRepository;
use App\Service\OrderTransitionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ExpirePartialAcceptanceMessageHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private OrderTransitionService $orderTransitionService,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private int $partialAcceptanceExpirationLeadSeconds,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExpirePartialAcceptanceMessage $message): void
    {
        $this->logger->debug('messenger.received', [
            'message' => ExpirePartialAcceptanceMessage::class,
            'order_id' => $message->orderId,
        ]);

        try {
            $this->handle($message);
            $this->logger->info('messenger.handled', [
                'message' => ExpirePartialAcceptanceMessage::class,
                'order_id' => $message->orderId,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('messenger.failure', [
                'message' => ExpirePartialAcceptanceMessage::class,
                'order_id' => $message->orderId,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function handle(ExpirePartialAcceptanceMessage $message): void
    {
        if (!Uuid::isValid($message->orderId)) {
            $this->logger->warning('messenger.skipped', [
                'message' => ExpirePartialAcceptanceMessage::class,
                'order_id' => $message->orderId,
                'reason' => 'invalid_uuid',
            ]);

            return;
        }

        $this->entityManager->wrapInTransaction(function () use ($message): void {
            $order = $this->orderRepository->find($message->orderId);
            if (null === $order || OrderStatus::PartiallyAccepted !== $order->getStatus()) {
                $this->logger->warning('messenger.skipped', [
                    'message' => ExpirePartialAcceptanceMessage::class,
                    'order_id' => $message->orderId,
                    'reason' => null === $order ? 'order_not_found' : 'wrong_status',
                ]);

                return;
            }

            $pickupSlot = $order->getPickupSlot();
            $now = $this->clock->now();
            if (null === $pickupSlot) {
                $this->logger->warning('messenger.skipped', [
                    'message' => ExpirePartialAcceptanceMessage::class,
                    'order_id' => $message->orderId,
                    'reason' => 'no_pickup_slot',
                ]);

                return;
            }

            $expiresAt = $pickupSlot->getStartsAt()->modify('-'.$this->partialAcceptanceExpirationLeadSeconds.' seconds');
            if ($now < $expiresAt) {
                return;
            }
            // Intentionally no guard on $now >= $pickupSlot->getStartsAt(): a delayed worker must
            // still cancel and release the slot even after the slot window has opened.

            $this->orderTransitionService->autoCancelPartialAcceptanceTimeout($order);
            $this->entityManager->flush();
        });
    }
}
