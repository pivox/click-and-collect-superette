<?php

declare(strict_types=1);

namespace App\Tests\Functional\Doctrine;

use App\Entity\Order;
use App\Entity\OrderStatusLog;
use App\Enum\OrderStatus;
use App\Tests\Functional\Api\FunctionalApiTestCase;

final class OrderStatusLogDoctrineTest extends FunctionalApiTestCase
{
    public function testOrderStatusLogCanBePersistedWithNullableNoteAndCreatedAt(): void
    {
        $customer = $this->createUser('customer-status-log@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);

        $log = new OrderStatusLog($order, OrderStatus::Submitted);

        $this->entityManager->persist($order);
        $this->entityManager->persist($log);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(OrderStatusLog::class)->find($log->getId());

        self::assertInstanceOf(OrderStatusLog::class, $found);
        self::assertSame($order->getId()->toRfc4122(), $found->getOrder()->getId()->toRfc4122());
        self::assertSame(OrderStatus::Submitted, $found->getStatus());
        self::assertNull($found->getNote());
        self::assertInstanceOf(\DateTimeImmutable::class, $found->getCreatedAt());
    }

    public function testOrderStatusLogCanStoreNote(): void
    {
        $customer = $this->createUser('customer-status-log-note@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);

        $log = new OrderStatusLog($order, OrderStatus::Rejected, 'Rupture de stock');

        $this->entityManager->persist($order);
        $this->entityManager->persist($log);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(OrderStatusLog::class)->find($log->getId());

        self::assertInstanceOf(OrderStatusLog::class, $found);
        self::assertSame('Rupture de stock', $found->getNote());
    }
}
