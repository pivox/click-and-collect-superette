<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\OrderStatusLog;
use App\Enum\OrderStatus;
use App\Message\ExpireMerchantResponseMessage;
use App\MessageHandler\ExpireMerchantResponseMessageHandler;
use App\Repository\OrderRepository;
use App\Service\NotificationService;
use App\Service\OrderTransitionService;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use App\Tests\Functional\OrderPickupFixtureTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class ExpireMerchantResponseMessageHandlerTest extends FunctionalApiTestCase
{
    use OrderPickupFixtureTrait;

    public function testHandlerCancelsSubmittedOrderAtTimeout(): void
    {
        $order = $this->createOrderWithStatus(OrderStatus::Submitted, new \DateTimeImmutable('2026-05-16 12:00:00'), booked: true);
        $handler = $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'));

        $handler(new ExpireMerchantResponseMessage($order->getId()->toRfc4122()));

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::Cancelled, $updatedOrder->getStatus());
        self::assertSame(0, $updatedOrder->getPickupSlot()?->getBookedCount());

        $logs = $this->findTimeoutLogs($updatedOrder);
        self::assertCount(1, $logs);

        $notifications = $this->findTimeoutNotifications($updatedOrder);
        self::assertCount(1, $notifications);
        self::assertSame('Commande annulée automatiquement', $notifications[0]->getTitleFr());
        self::assertSame('تم إلغاء الطلب آليًا', $notifications[0]->getTitleAr());
    }

    public function testHandlerDoesNothingBeforeTimeoutWindow(): void
    {
        $order = $this->createOrderWithStatus(OrderStatus::Submitted, new \DateTimeImmutable('2026-05-16 13:00:00'), booked: true);
        $handler = $this->createHandler(new \DateTimeImmutable('2026-05-16 10:30:00'));

        $handler(new ExpireMerchantResponseMessage($order->getId()->toRfc4122()));

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::Submitted, $updatedOrder->getStatus());
        self::assertSame(1, $updatedOrder->getPickupSlot()?->getBookedCount());
        self::assertCount(0, $this->findTimeoutLogs($updatedOrder));
        self::assertCount(0, $this->findTimeoutNotifications($updatedOrder));
    }

    #[DataProvider('nonSubmittedStatuses')]
    public function testHandlerDoesNothingForNonSubmittedOrders(OrderStatus $status): void
    {
        $order = $this->createOrderWithStatus($status, new \DateTimeImmutable('2026-05-16 12:00:00'), booked: true);

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(
            new ExpireMerchantResponseMessage($order->getId()->toRfc4122())
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
        $order = $this->createOrderWithStatus(OrderStatus::Submitted, new \DateTimeImmutable('2026-05-16 09:59:00'), booked: true);

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(
            new ExpireMerchantResponseMessage($order->getId()->toRfc4122())
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::Submitted, $updatedOrder->getStatus());
        self::assertSame(1, $updatedOrder->getPickupSlot()?->getBookedCount());
    }

    public function testHandlerDoesNothingWhenOrderHasNoPickupSlot(): void
    {
        $order = $this->createSubmittedOrderWithoutSlot();

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(
            new ExpireMerchantResponseMessage($order->getId()->toRfc4122())
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::Submitted, $updatedOrder->getStatus());
        self::assertCount(0, $this->findTimeoutLogs($updatedOrder));
        self::assertCount(0, $this->findTimeoutNotifications($updatedOrder));
    }

    public function testHandlerIsIdempotent(): void
    {
        $order = $this->createOrderWithStatus(OrderStatus::Submitted, new \DateTimeImmutable('2026-05-16 12:00:00'), booked: true);
        $handler = $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'));
        $message = new ExpireMerchantResponseMessage($order->getId()->toRfc4122());

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

    public function testTimeoutNotificationIsVisibleOnlyToCustomerOwnerViaNotificationApi(): void
    {
        $otherCustomer = $this->createUser('other-timeout-notification-'.Uuid::v4().'@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createOrderWithStatus(OrderStatus::Submitted, new \DateTimeImmutable('2026-05-16 12:00:00'), booked: true);

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(
            new ExpireMerchantResponseMessage($order->getId()->toRfc4122())
        );

        $ownerResponse = $this->requestJson('GET', '/api/me/notifications', null, $order->getCustomer());
        $ownerPayload = $this->decodeJson($ownerResponse);
        self::assertSame(1, $ownerPayload['total']);
        self::assertSame('Commande annulée automatiquement', $ownerPayload['items'][0]['title_fr']);
        self::assertSame('Votre Kadhia a été annulée car le marchand n’a pas répondu à temps.', $ownerPayload['items'][0]['body_fr']);

        $otherResponse = $this->requestJson('GET', '/api/me/notifications', null, $otherCustomer);
        $otherPayload = $this->decodeJson($otherResponse);
        self::assertSame(0, $otherPayload['total']);
    }

    /**
     * @return iterable<string, array{OrderStatus}>
     */
    public static function nonSubmittedStatuses(): iterable
    {
        yield 'accepted' => [OrderStatus::Accepted];
        yield 'partially_accepted' => [OrderStatus::PartiallyAccepted];
        yield 'preparing' => [OrderStatus::Preparing];
        yield 'ready' => [OrderStatus::Ready];
        yield 'pickup_pending' => [OrderStatus::PickupPending];
        yield 'completed' => [OrderStatus::Completed];
        yield 'rejected' => [OrderStatus::Rejected];
        yield 'cancelled' => [OrderStatus::Cancelled];
    }

    private function createHandler(\DateTimeImmutable $now): ExpireMerchantResponseMessageHandler
    {
        return new ExpireMerchantResponseMessageHandler(
            self::getContainer()->get(OrderRepository::class),
            self::getContainer()->get(OrderTransitionService::class),
            $this->entityManager,
            new MockClock($now),
            7200,
            new NullLogger(),
        );
    }

    private function createOrderWithStatus(OrderStatus $status, \DateTimeImmutable $slotStartsAt, bool $booked = false): Order
    {
        $customer = $this->createUser('customer-timeout-'.$status->value.'-'.Uuid::v4().'@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-timeout-'.$status->value.'-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
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

    private function createSubmittedOrderWithoutSlot(): Order
    {
        $customer = $this->createUser('customer-timeout-no-slot-'.Uuid::v4().'@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-timeout-no-slot-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
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
            'note' => OrderTransitionService::MERCHANT_RESPONSE_TIMEOUT_NOTE,
        ]);
    }

    /**
     * @return list<Notification>
     */
    private function findTimeoutNotifications(Order $order): array
    {
        return $this->entityManager->getRepository(Notification::class)->findBy([
            'order' => $order,
            'type' => NotificationService::TYPE_MERCHANT_RESPONSE_TIMEOUT,
        ]);
    }
}
