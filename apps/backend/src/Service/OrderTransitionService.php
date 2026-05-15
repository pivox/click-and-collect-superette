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
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderStatusLogRecorder $orderStatusLogRecorder,
        private PickupSessionRepository $pickupSessionRepository,
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

    public function markCompleted(Order $order): void
    {
        $order->complete();
        $this->orderStatusLogRecorder->record($order, OrderStatus::Completed);
    }
}
