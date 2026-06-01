<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\PickupSession;
use App\Enum\OrderStatus;
use App\Repository\PickupSessionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrderTransitionService
{
    public const MERCHANT_RESPONSE_TIMEOUT_NOTE = 'AUTO_CANCELLED_MERCHANT_RESPONSE_TIMEOUT';
    public const PARTIAL_ACCEPTANCE_TIMEOUT_NOTE = 'AUTO_CANCELLED_PARTIAL_ACCEPTANCE_TIMEOUT';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderStatusLogRecorder $orderStatusLogRecorder,
        private PickupSessionRepository $pickupSessionRepository,
        private NotificationService $notificationService,
    ) {
    }

    public function markReady(Order $order): PickupSession
    {
        $order->markReady();
        $this->orderStatusLogRecorder->record($order, OrderStatus::Ready);

        $pickupSession = $this->pickupSessionRepository->findOneByOrder($order);
        if (null === $pickupSession) {
            $pickupSession = new PickupSession($order);
            $this->entityManager->persist($pickupSession);
        }

        return $pickupSession;
    }

    public function markPickupPending(Order $order): void
    {
        $order->startPickup();
        $this->orderStatusLogRecorder->record($order, OrderStatus::PickupPending);
    }

    public function markCompleted(Order $order, ?string $note = null): void
    {
        $order->complete();
        $this->orderStatusLogRecorder->record($order, OrderStatus::Completed, $note);
        $this->notificationService->notifyCustomerOrderCompleted($order);
        $this->notificationService->notifyMerchantPickupCompleted($order);
    }

    public function completeByCode(Order $order, string $code): void
    {
        $order->redeemByCode($code);
        $this->pickupSessionRepository->findOneByOrder($order)?->completeByCode();
        $this->orderStatusLogRecorder->record($order, OrderStatus::Completed, 'withdrawal_validated_by_code');
        $this->notificationService->notifyCustomerOrderCompleted($order);
        $this->notificationService->notifyMerchantPickupCompleted($order);
    }

    public function completeManually(Order $order, string $note): void
    {
        $order->completeManually();
        $this->orderStatusLogRecorder->record(
            $order,
            OrderStatus::Completed,
            \sprintf('withdrawal_validated_manually: %s', $note),
        );
        $this->notificationService->notifyCustomerOrderCompleted($order);
        $this->notificationService->notifyMerchantPickupCompleted($order);
    }

    public function autoCancelMerchantResponseTimeout(Order $order): void
    {
        if (OrderStatus::Submitted !== $order->getStatus()) {
            return;
        }

        $order->cancel();

        $pickupSlot = $order->getPickupSlot();
        if (null !== $pickupSlot) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE pickup_slots SET booked_count = CASE WHEN booked_count > 0 THEN booked_count - 1 ELSE 0 END WHERE id = :id',
                ['id' => $pickupSlot->getId()->toBinary()],
            );
        }

        $this->orderStatusLogRecorder->record($order, OrderStatus::Cancelled, self::MERCHANT_RESPONSE_TIMEOUT_NOTE);
        $this->notificationService->notifyCustomerMerchantResponseTimeout($order);
    }

    public function autoCancelPartialAcceptanceTimeout(Order $order): void
    {
        if (OrderStatus::PartiallyAccepted !== $order->getStatus()) {
            return;
        }

        $order->cancelPartialAcceptanceTimeout();

        $pickupSlot = $order->getPickupSlot();
        if (null !== $pickupSlot) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE pickup_slots SET booked_count = CASE WHEN booked_count > 0 THEN booked_count - 1 ELSE 0 END WHERE id = :id',
                ['id' => $pickupSlot->getId()->toBinary()],
            );
        }

        $this->orderStatusLogRecorder->record($order, OrderStatus::Cancelled, self::PARTIAL_ACCEPTANCE_TIMEOUT_NOTE);
        $this->notificationService->notifyCustomerPartialAcceptanceTimeout($order);
    }
}
