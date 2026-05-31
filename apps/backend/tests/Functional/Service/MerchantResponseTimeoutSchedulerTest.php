<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\Order;
use App\Entity\OrderLine;
use App\Message\ExpireMerchantResponseMessage;
use App\Service\MerchantResponseTimeoutScheduler;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use App\Tests\Functional\OrderPickupFixtureTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Uuid;

final class MerchantResponseTimeoutSchedulerTest extends FunctionalApiTestCase
{
    use OrderPickupFixtureTrait;

    public function testSubmittedOrderWithSlotMoreThanTwoHoursAwayDispatchesDelayedExpiration(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new MerchantResponseRecordingMessageBus();
        $scheduler = new MerchantResponseTimeoutScheduler($bus, new MockClock($now), 7200);

        $order = $this->createSubmittedOrderWithSlot($now->modify('+3 hours'));

        $scheduler->scheduleForSubmittedOrder($order);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(ExpireMerchantResponseMessage::class, $bus->dispatched[0]->getMessage());
        $delayStamp = $bus->dispatched[0]->last(DelayStamp::class);
        self::assertInstanceOf(DelayStamp::class, $delayStamp);
        self::assertSame(3_600_000, $delayStamp->getDelay());
    }

    public function testSubmittedOrderWithStoredLocalClockSlotUsesTunisInstantForTimeout(): void
    {
        $now = new \DateTimeImmutable('2026-05-16T10:00:00+00:00');
        $bus = new MerchantResponseRecordingMessageBus();
        $scheduler = new MerchantResponseTimeoutScheduler($bus, new MockClock($now), 7200);

        $order = $this->createSubmittedOrderWithSlot($now->modify('+2 hours'));
        $orderId = $order->getId();
        $this->entityManager->clear();
        $order = $this->entityManager->getRepository(Order::class)->find($orderId);
        self::assertNotNull($order);

        $scheduler->scheduleForSubmittedOrder($order);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(ExpireMerchantResponseMessage::class, $bus->dispatched[0]->getMessage());
        self::assertNull($bus->dispatched[0]->last(DelayStamp::class));
    }

    public function testSubmittedOrderInsideTimeoutWindowDispatchesImmediately(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new MerchantResponseRecordingMessageBus();
        $scheduler = new MerchantResponseTimeoutScheduler($bus, new MockClock($now), 7200);

        $order = $this->createSubmittedOrderWithSlot($now->modify('+90 minutes'));

        $scheduler->scheduleForSubmittedOrder($order);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(ExpireMerchantResponseMessage::class, $bus->dispatched[0]->getMessage());
        self::assertNull($bus->dispatched[0]->last(DelayStamp::class));
    }

    public function testSubmittedOrderAfterSlotStartDoesNotDispatchExpiration(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new MerchantResponseRecordingMessageBus();
        $scheduler = new MerchantResponseTimeoutScheduler($bus, new MockClock($now), 7200);

        $order = $this->createSubmittedOrderWithSlot($now->modify('-1 minute'));

        $scheduler->scheduleForSubmittedOrder($order);

        self::assertCount(0, $bus->dispatched);
    }

    public function testNonSubmittedOrderDoesNotDispatchExpiration(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new MerchantResponseRecordingMessageBus();
        $scheduler = new MerchantResponseTimeoutScheduler($bus, new MockClock($now), 7200);

        $order = $this->createSubmittedOrderWithSlot($now->modify('+3 hours'));
        $order->accept();

        $scheduler->scheduleForSubmittedOrder($order);

        self::assertCount(0, $bus->dispatched);
    }

    public function testSubmittedOrderWithoutSlotDoesNotDispatchExpiration(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new MerchantResponseRecordingMessageBus();
        $scheduler = new MerchantResponseTimeoutScheduler($bus, new MockClock($now), 7200);

        $order = $this->createSubmittedOrderWithoutSlot();

        $scheduler->scheduleForSubmittedOrder($order);

        self::assertCount(0, $bus->dispatched);
    }

    private function createSubmittedOrderWithSlot(\DateTimeImmutable $slotStartsAt): Order
    {
        $customer = $this->createUser('customer-timeout-scheduler-'.Uuid::v4().'@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-timeout-scheduler-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop, $slotStartsAt);
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
}

final class MerchantResponseRecordingMessageBus implements MessageBusInterface
{
    /** @var list<Envelope> */
    public array $dispatched = [];

    /**
     * @param list<StampInterface> $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);
        $this->dispatched[] = $envelope;

        return $envelope;
    }
}
