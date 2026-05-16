<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\PickupSlot;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductReferenceStatus;
use App\Message\SendPickupReminderMessage;
use App\Service\PickupReminderScheduler;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Uuid;

final class PickupReminderSchedulerTest extends FunctionalApiTestCase
{
    public function testReadyOrderWithSlotMoreThanOneHourAwayDispatchesDelayedReminder(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new RecordingMessageBus();
        $scheduler = new PickupReminderScheduler($bus, new MockClock($now));

        $order = $this->createReadyOrderWithSlot($now->modify('+90 minutes'));

        $scheduler->scheduleForReadyOrder($order);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(SendPickupReminderMessage::class, $bus->dispatched[0]->getMessage());
        $delayStamp = $bus->dispatched[0]->last(DelayStamp::class);
        self::assertInstanceOf(DelayStamp::class, $delayStamp);
        self::assertSame(1_800_000, $delayStamp->getDelay());
    }

    public function testReadyOrderWithSlotLessThanOneHourAwayDispatchesImmediately(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new RecordingMessageBus();
        $scheduler = new PickupReminderScheduler($bus, new MockClock($now));

        $order = $this->createReadyOrderWithSlot($now->modify('+30 minutes'));

        $scheduler->scheduleForReadyOrder($order);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(SendPickupReminderMessage::class, $bus->dispatched[0]->getMessage());
        self::assertNull($bus->dispatched[0]->last(DelayStamp::class));
    }

    public function testReadyOrderAfterSlotStartDoesNotDispatchReminder(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $bus = new RecordingMessageBus();
        $scheduler = new PickupReminderScheduler($bus, new MockClock($now));

        $order = $this->createReadyOrderWithSlot($now->modify('-1 minute'));

        $scheduler->scheduleForReadyOrder($order);

        self::assertCount(0, $bus->dispatched);
    }

    private function createReadyOrderWithSlot(\DateTimeImmutable $slotStartsAt): Order
    {
        $customer = $this->createUser('customer-scheduler-'.Uuid::v4().'@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-scheduler-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop, $slotStartsAt);
        $product = $this->createMerchantProduct($shop);

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setPickupSlot($slot);
        $order->submit();
        $order->accept();
        $order->startPreparing();

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd($product->getPriceTnd())
            ->setLineTotalTnd($product->getPriceTnd())
            ->markPrepared(true);
        $order->addLine($line);
        $order->recomputeTotal();
        $order->markReady();

        $this->entityManager->persist($order);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $order;
    }

    private function createPickupSlot(Shop $shop, \DateTimeImmutable $startsAt): PickupSlot
    {
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($startsAt)
            ->setEndsAt($startsAt->modify('+1 hour'))
            ->setCapacity(5);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }

    private function createMerchantProduct(Shop $shop): MerchantProduct
    {
        $id = Uuid::v4()->toRfc4122();
        $brand = (new Brand())
            ->setCanonicalName('Brand Reminder '.$id)
            ->setSlug('brand-reminder-'.$id);
        $category = (new Category())
            ->setNameFr('Cat Reminder '.$id)
            ->setSlug('cat-reminder-'.$id);
        $ref = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit rappel')
            ->setStatus(ProductReferenceStatus::Approved);
        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd('2.000');

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ref);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }
}

final class RecordingMessageBus implements MessageBusInterface
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
