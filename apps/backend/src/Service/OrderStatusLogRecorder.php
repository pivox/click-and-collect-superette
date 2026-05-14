<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderStatusLog;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrderStatusLogRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function record(Order $order, OrderStatus $status, ?string $note = null): OrderStatusLog
    {
        $log = new OrderStatusLog($order, $status, $note);
        $this->entityManager->persist($log);

        return $log;
    }
}
