<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrderNumberGenerator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository,
    ) {
    }

    public function assignNextIfMissing(Order $order): void
    {
        if (null !== $order->getOrderNumber()) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $connection->executeStatement(
                'SELECT pg_advisory_xact_lock(hashtext(:lock_key))',
                ['lock_key' => 'orders:'.$order->getShop()->getId()->toRfc4122()],
            );
        }

        $order->assignOrderNumber($this->orderRepository->nextOrderNumberForShop($order->getShop()));
    }
}
