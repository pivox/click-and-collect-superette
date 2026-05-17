<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\OrderStatusLog;
use App\Enum\OrderStatus;
use App\Message\ExpirePartialAcceptanceMessage;
use App\MessageHandler\ExpirePartialAcceptanceMessageHandler;
use App\Service\NotificationService;
use App\Service\OrderTransitionService;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use App\Tests\Functional\OrderPickupFixtureTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class ExpirePartialAcceptanceMessageHandlerTest extends FunctionalApiTestCase
{
    use OrderPickupFixtureTrait;

    public function testHandlerCancelsPartiallyAcceptedOrderAtExpiration(): void
    {
        $order = $this->createOrderWithStatus(OrderStatus::PartiallyAccepted, new \DateTimeImmutable('2026-05-16 12:00:00'), booked: true);

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(
            new ExpirePartialAcceptanceMessage($order->getId()->toRfc4122())
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::Cancelled, $updatedOrder->getStatus());
        self::assertSame(0, $updatedOrder->getPickupSlot()?->getBookedCount());
        self::assertCount(1, $this->findTimeoutLogs($updatedOrder));

        $notifications = $this->findTimeoutNotifications($updatedOrder);
        self::assertCount(1, $notifications);
        self::assertSame('Commande annulée automatiquement', $notifications[0]->getTitleFr());
        self::assertSame('تم إلغاء الطلب آليًا', $notifications[0]->getTitleAr());
    }

    public function testHandlerDoesNothingBeforeExpirationWindow(): void
    {
        $order = $this->createOrderWithStatus(OrderStatus::PartiallyAccepted, new \DateTimeImmutable('2026-05-16 13:00:00'), booked: true);

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:30:00'))(
            new ExpirePartialAcceptanceMessage($order->getId()->toRfc4122())
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::PartiallyAccepted, $updatedOrder->getStatus());
        self::assertSame(1, $updatedOrder->getPickupSlot()?->getBookedCount());
        self::assertCount(0, $this->findTimeoutLogs($updatedOrder));
        self::assertCount(0, $this->findTimeoutNotifications($updatedOrder));
    }

    #[DataProvider('nonPartiallyAcceptedStatuses')]
    public function testHandlerDoesNothingForNonPartiallyAcceptedOrders(OrderStatus $status): void
    {
        $order = $this->createOrderWithStatus($status, new \DateTimeImmutable('2026-05-16 12:00:00'), booked: true);

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(
            new ExpirePartialAcceptanceMessage($order->getId()->toRfc4122())
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame($status, $updatedOrder->getStatus());
        self::assertSame(1, $updatedOrder->getPickupSlot()?->getBookedCount());
        self::assertCount(0, $this->findTimeoutLogs($updatedOrder));
        self::assertCount(0, $this->findTimeoutNotifications($updatedOrder));
    }

    public function testHandlerDoesNothingAfterSlotStart(): void
    {
        $order = $this->createOrderWithStatus(OrderStatus::PartiallyAccepted, new \DateTimeImmutable('2026-05-16 09:59:00'), booked: true);

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(
            new ExpirePartialAcceptanceMessage($order->getId()->toRfc4122())
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::PartiallyAccepted, $updatedOrder->getStatus());
        self::assertSame(1, $updatedOrder->getPickupSlot()?->getBookedCount());
    }

    public function testHandlerDoesNothingWhenOrderHasNoPickupSlot(): void
    {
        $order = $this->createPartiallyAcceptedOrderWithoutSlot();

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(
            new ExpirePartialAcceptanceMessage($order->getId()->toRfc4122())
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::PartiallyAccepted, $updatedOrder->getStatus());
        self::assertCount(0, $this->findTimeoutLogs($updatedOrder));
        self::assertCount(0, $this->findTimeoutNotifications($updatedOrder));
    }

    public function testHandlerIsIdempotent(): void
    {
        $order = $this->createOrderWithStatus(OrderStatus::PartiallyAccepted, new \DateTimeImmutable('2026-05-16 12:00:00'), booked: true);
        $handler = $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'));
        $message = new ExpirePartialAcceptanceMessage($order->getId()->toRfc4122());

        $handler($message);
        $handler($message);

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::Cancelled, $updatedOrder->getStatus());
        self::assertSame(0, $updatedOrder->getPickupSlot()?->getBookedCount());
        self::assertCount(1, $this->findTimeoutLogs($updatedOrder));
        self::assertCount(1, $this->findTimeoutNotifications($updatedOrder));
    }

    public function testInvalidOrderIdDoesNothing(): void
    {
        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(
            new ExpirePartialAcceptanceMessage('not-a-uuid')
        );

        self::assertSame(0, $this->entityManager->getRepository(Notification::class)->count([]));
    }

    /**
     * @return iterable<string, array{OrderStatus}>
     */
    public static function nonPartiallyAcceptedStatuses(): iterable
    {
        yield 'submitted' => [OrderStatus::Submitted];
        yield 'accepted' => [OrderStatus::Accepted];
        yield 'preparing' => [OrderStatus::Preparing];
        yield 'ready' => [OrderStatus::Ready];
        yield 'pickup_pending' => [OrderStatus::PickupPending];
        yield 'completed' => [OrderStatus::Completed];
        yield 'rejected' => [OrderStatus::Rejected];
        yield 'cancelled' => [OrderStatus::Cancelled];
    }

    private function createHandler(\DateTimeImmutable $now): ExpirePartialAcceptanceMessageHandler
    {
        return new ExpirePartialAcceptanceMessageHandler(
            $this->entityManager->getRepository(Order::class),
            self::getContainer()->get(OrderTransitionService::class),
            $this->entityManager,
            new MockClock($now),
            7200,
        );
    }

    private function createOrderWithStatus(OrderStatus $status, \DateTimeImmutable $slotStartsAt, bool $booked = false): Order
    {
        $customer = $this->createUser('customer-partial-expire-'.$status->value.'-'.Uuid::v4().'@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-partial-expire-'.$status->value.'-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop, $slotStartsAt);
        if ($booked) {
            $slot->book();
        }
        $product = $this->createMerchantProduct($shop);

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setPickupSlot($slot);

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd($product->getPriceTnd())
            ->setLineTotalTnd($product->getPriceTnd());
        $order->addLine($line);
        $order->recomputeTotal();
        $order->submit();

        match ($status) {
            OrderStatus::Submitted => null,
            OrderStatus::Accepted => $order->accept(),
            OrderStatus::PartiallyAccepted => $order->partiallyAccept('Rupture'),
            OrderStatus::Preparing => $this->startPreparing($order),
            OrderStatus::Ready => $this->markReady($order, $line),
            OrderStatus::PickupPending => $this->startPickup($order, $line),
            OrderStatus::Completed => $this->complete($order, $line),
            OrderStatus::Rejected => $order->reject('Rupture'),
            OrderStatus::Cancelled => $order->cancel(),
            default => throw new \InvalidArgumentException('Unsupported status fixture.'),
        };

        $this->entityManager->persist($order);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $order;
    }

    private function createPartiallyAcceptedOrderWithoutSlot(): Order
    {
        $customer = $this->createUser('customer-partial-expire-no-slot-'.Uuid::v4().'@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-partial-expire-no-slot-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd($product->getPriceTnd())
            ->setLineTotalTnd($product->getPriceTnd());
        $order->addLine($line);
        $order->recomputeTotal();
        $order->submit();
        $order->partiallyAccept('Rupture');

        $this->entityManager->persist($order);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $order;
    }

    private function startPreparing(Order $order): void
    {
        $order->accept();
        $order->startPreparing();
    }

    private function markReady(Order $order, OrderLine $line): void
    {
        $this->startPreparing($order);
        $line->markPrepared(true);
        $order->markReady();
    }

    private function startPickup(Order $order, OrderLine $line): void
    {
        $this->markReady($order, $line);
        $order->startPickup();
    }

    private function complete(Order $order, OrderLine $line): void
    {
        $this->startPickup($order, $line);
        $order->complete();
    }

    /**
     * @return list<OrderStatusLog>
     */
    private function findTimeoutLogs(Order $order): array
    {
        return $this->entityManager->getRepository(OrderStatusLog::class)->findBy([
            'order' => $order,
            'status' => OrderStatus::Cancelled,
            'note' => OrderTransitionService::PARTIAL_ACCEPTANCE_TIMEOUT_NOTE,
        ]);
    }

    /**
     * @return list<Notification>
     */
    private function findTimeoutNotifications(Order $order): array
    {
        return $this->entityManager->getRepository(Notification::class)->findBy([
            'order' => $order,
            'type' => NotificationService::TYPE_PARTIAL_ACCEPTANCE_TIMEOUT,
        ]);
    }
}
