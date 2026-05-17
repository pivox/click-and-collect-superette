<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\Order;
use App\Entity\OrderLine;
use App\Message\ExpirePartialAcceptanceMessage;
use App\Message\PartialAcceptanceReminderMessage;
use App\Service\PartialAcceptanceExpirationScheduler;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use App\Tests\Functional\OrderPickupFixtureTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Uuid;

final class PartialAcceptanceExpirationSchedulerTest extends FunctionalApiTestCase
{
    use OrderPickupFixtureTrait;

    public function testPartiallyAcceptedOrderDispatchesDelayedReminderAndExpiration(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new PartialAcceptanceRecordingMessageBus();
        $scheduler = new PartialAcceptanceExpirationScheduler($bus, new MockClock($now), 14400, 7200);

        $order = $this->createPartiallyAcceptedOrderWithSlot($now->modify('+5 hours'));

        $scheduler->scheduleForPartiallyAcceptedOrder($order);

        self::assertCount(2, $bus->dispatched);
        $reminderMsg = $bus->dispatched[0]->getMessage();
        self::assertInstanceOf(PartialAcceptanceReminderMessage::class, $reminderMsg);
        self::assertTrue(Uuid::isValid($reminderMsg->cycleId), 'cycleId must be a valid UUID');
        self::assertInstanceOf(ExpirePartialAcceptanceMessage::class, $bus->dispatched[1]->getMessage());
        self::assertSame(3_600_000, $bus->dispatched[0]->last(DelayStamp::class)?->getDelay());
        self::assertSame(10_800_000, $bus->dispatched[1]->last(DelayStamp::class)?->getDelay());
    }

    public function testReminderDispatchesImmediatelyWhenReminderWindowAlreadyStarted(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new PartialAcceptanceRecordingMessageBus();
        $scheduler = new PartialAcceptanceExpirationScheduler($bus, new MockClock($now), 14400, 7200);

        $order = $this->createPartiallyAcceptedOrderWithSlot($now->modify('+3 hours'));

        $scheduler->scheduleForPartiallyAcceptedOrder($order);

        self::assertCount(2, $bus->dispatched);
        $reminderMsg = $bus->dispatched[0]->getMessage();
        self::assertInstanceOf(PartialAcceptanceReminderMessage::class, $reminderMsg);
        self::assertTrue(Uuid::isValid($reminderMsg->cycleId), 'cycleId must be a valid UUID');
        self::assertNull($bus->dispatched[0]->last(DelayStamp::class));
        self::assertInstanceOf(ExpirePartialAcceptanceMessage::class, $bus->dispatched[1]->getMessage());
        self::assertSame(3_600_000, $bus->dispatched[1]->last(DelayStamp::class)?->getDelay());
    }

    public function testExpirationDispatchesImmediatelyWhenExpirationWindowAlreadyStarted(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new PartialAcceptanceRecordingMessageBus();
        $scheduler = new PartialAcceptanceExpirationScheduler($bus, new MockClock($now), 14400, 7200);

        $order = $this->createPartiallyAcceptedOrderWithSlot($now->modify('+90 minutes'));

        $scheduler->scheduleForPartiallyAcceptedOrder($order);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(ExpirePartialAcceptanceMessage::class, $bus->dispatched[0]->getMessage());
        self::assertNull($bus->dispatched[0]->last(DelayStamp::class));
    }

    public function testStartedSlotDoesNotDispatchMessages(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new PartialAcceptanceRecordingMessageBus();
        $scheduler = new PartialAcceptanceExpirationScheduler($bus, new MockClock($now), 14400, 7200);

        $order = $this->createPartiallyAcceptedOrderWithSlot($now->modify('-1 minute'));

        $scheduler->scheduleForPartiallyAcceptedOrder($order);

        self::assertCount(0, $bus->dispatched);
    }

    public function testNonPartiallyAcceptedOrderDoesNotDispatchMessages(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new PartialAcceptanceRecordingMessageBus();
        $scheduler = new PartialAcceptanceExpirationScheduler($bus, new MockClock($now), 14400, 7200);

        $order = $this->createPartiallyAcceptedOrderWithSlot($now->modify('+5 hours'));
        $order->resubmit();

        $scheduler->scheduleForPartiallyAcceptedOrder($order);

        self::assertCount(0, $bus->dispatched);
    }

    public function testPartiallyAcceptedOrderWithoutSlotDoesNotDispatchMessages(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new PartialAcceptanceRecordingMessageBus();
        $scheduler = new PartialAcceptanceExpirationScheduler($bus, new MockClock($now), 14400, 7200);

        $order = $this->createPartiallyAcceptedOrderWithoutSlot();

        $scheduler->scheduleForPartiallyAcceptedOrder($order);

        self::assertCount(0, $bus->dispatched);
    }

    private function createPartiallyAcceptedOrderWithSlot(\DateTimeImmutable $slotStartsAt): Order
    {
        $customer = $this->createUser('customer-partial-scheduler-'.Uuid::v4().'@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-partial-scheduler-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
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
        $order->partiallyAccept('Rupture');

        $this->entityManager->persist($order);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $order;
    }

    private function createPartiallyAcceptedOrderWithoutSlot(): Order
    {
        $customer = $this->createUser('customer-partial-no-slot-'.Uuid::v4().'@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-partial-no-slot-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
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
}

final class PartialAcceptanceRecordingMessageBus implements MessageBusInterface
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
