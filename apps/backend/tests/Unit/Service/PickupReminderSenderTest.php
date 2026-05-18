<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\PickupReminderNotifierInterface;
use App\Service\PickupReminderSender;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class PickupReminderSenderTest extends TestCase
{
    public function testWindowBoundsAreComputedCorrectly(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $repository = $this->createMock(OrderRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOrdersNeedingPickupReminder')
            ->with(
                new \DateTimeImmutable('2026-05-16 10:55:00'),
                new \DateTimeImmutable('2026-05-16 11:05:00'),
            )
            ->willReturn([]);

        $this->buildSender($now, $repository)->sendDueReminders();
    }

    public function testWindowShiftsWithDifferentNow(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 09:30:00');
        $repository = $this->createMock(OrderRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOrdersNeedingPickupReminder')
            ->with(
                new \DateTimeImmutable('2026-05-16 10:25:00'),
                new \DateTimeImmutable('2026-05-16 10:35:00'),
            )
            ->willReturn([]);

        $this->buildSender($now, $repository)->sendDueReminders();
    }

    public function testReturnsZeroAndSkipsFlushWhenNoOrdersFound(): void
    {
        $repository = $this->createStub(OrderRepository::class);
        $repository->method('findOrdersNeedingPickupReminder')->willReturn([]);

        $notifier = $this->createMock(PickupReminderNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyCustomerPickupReminder');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $count = $this->buildSender(new \DateTimeImmutable(), $repository, $notifier, $entityManager)
            ->sendDueReminders();

        self::assertSame(0, $count);
    }

    public function testNotifiesEachEligibleOrder(): void
    {
        $order1 = $this->createStub(Order::class);
        $order2 = $this->createStub(Order::class);

        $repository = $this->createStub(OrderRepository::class);
        $repository->method('findOrdersNeedingPickupReminder')->willReturn([$order1, $order2]);

        $notifier = $this->createMock(PickupReminderNotifierInterface::class);
        $notifier->expects(self::exactly(2))->method('notifyCustomerPickupReminder');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $count = $this->buildSender(new \DateTimeImmutable(), $repository, $notifier, $entityManager)
            ->sendDueReminders();

        self::assertSame(2, $count);
    }

    public function testFlushCalledOnceForMultipleOrders(): void
    {
        $repository = $this->createStub(OrderRepository::class);
        $repository->method('findOrdersNeedingPickupReminder')->willReturn([
            $this->createStub(Order::class),
            $this->createStub(Order::class),
            $this->createStub(Order::class),
        ]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $notifier = $this->createStub(PickupReminderNotifierInterface::class);

        $this->buildSender(new \DateTimeImmutable(), $repository, $notifier, $entityManager)
            ->sendDueReminders();
    }

    private function buildSender(
        \DateTimeImmutable $now,
        ?OrderRepository $repository = null,
        ?PickupReminderNotifierInterface $notifier = null,
        ?EntityManagerInterface $entityManager = null,
    ): PickupReminderSender {
        return new PickupReminderSender(
            $repository ?? $this->createStub(OrderRepository::class),
            $notifier ?? $this->createStub(PickupReminderNotifierInterface::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            new MockClock($now),
        );
    }
}
