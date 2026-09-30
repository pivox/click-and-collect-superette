<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Kadhia;
use App\Entity\KadhiaLine;
use App\Entity\MerchantProduct;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\PickupSlot;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\ShopOrderingPolicy;
use App\Entity\User;
use App\Enum\KadhiaStatus;
use App\Enum\OrderStatus;
use App\Enum\ProductReferenceStatus;
use App\Service\PickupSlotDisplayTime;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

/**
 * ORDER-LEAD-002: minimum pickup lead time applied to the public slot
 * listing and revalidated at submission.
 */
final class PickupSlotLeadTimeApiTest extends FunctionalApiTestCase
{
    // GET /api/stores/{storeId}/pickup-slots

    public function testListingHidesSlotsBeforeLeadTimeAndExposesBookingPolicy(): void
    {
        $shop = $this->createShop();
        $this->setLeadTimeMinutes($shop, 240);
        $tooClose = $this->createPickupSlot($shop, startsAtModifier: '+2 hours', endsAtModifier: '+3 hours');
        $eligible = $this->createPickupSlot($shop, startsAtModifier: '+6 hours', endsAtModifier: '+7 hours');

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        $ids = array_column($payload['items'], 'id');
        self::assertNotContains($tooClose->getId()->toRfc4122(), $ids);
        self::assertContains($eligible->getId()->toRfc4122(), $ids);

        self::assertSame(240, $payload['booking_policy']['minimum_pickup_lead_time_minutes']);
        self::assertArrayHasKey('earliest_bookable_at', $payload['booking_policy']);
    }

    public function testListingWithZeroLeadTimeKeepsAllFutureSlotsAndExposesPolicy(): void
    {
        $shop = $this->createShop();
        $soon = $this->createPickupSlot($shop, startsAtModifier: '+2 hours', endsAtModifier: '+3 hours');
        $later = $this->createPickupSlot($shop, startsAtModifier: '+6 hours', endsAtModifier: '+7 hours');

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        $ids = array_column($payload['items'], 'id');
        self::assertContains($soon->getId()->toRfc4122(), $ids);
        self::assertContains($later->getId()->toRfc4122(), $ids);

        self::assertSame(0, $payload['booking_policy']['minimum_pickup_lead_time_minutes']);
        self::assertArrayHasKey('earliest_bookable_at', $payload['booking_policy']);
    }

    public function testListingWithDateParamStillAppliesLeadTime(): void
    {
        $shop = $this->createShop();
        $this->setLeadTimeMinutes($shop, ShopOrderingPolicy::MAX_MINIMUM_PICKUP_LEAD_TIME_MINUTES);
        $tomorrowSlot = $this->createPickupSlot($shop, startsAtModifier: '+26 hours', endsAtModifier: '+27 hours');

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/stores/%s/pickup-slots?date=tomorrow', $shop->getId()),
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertNotContains($tomorrowSlot->getId()->toRfc4122(), array_column($payload['items'], 'id'));
        self::assertSame(
            ShopOrderingPolicy::MAX_MINIMUM_PICKUP_LEAD_TIME_MINUTES,
            $payload['booking_policy']['minimum_pickup_lead_time_minutes'],
        );
    }

    // POST /api/me/kadhias/{kadhiaId}/submit

    public function testSubmitTooCloseSlotReturns422WithoutSideEffects(): void
    {
        $customer = $this->createUser('lead-submit-rejected@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $this->setLeadTimeMinutes($shop, 240);
        $slot = $this->createPickupSlot($shop, startsAtModifier: '+2 hours', endsAtModifier: '+3 hours');
        $product = $this->createMerchantProduct($shop, '2.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('PICKUP_SLOT_MINIMUM_LEAD_TIME_NOT_MET', $this->decodeJson($response)['detail']);

        $this->entityManager->clear();

        // Kadhia preserved as draft, no order, no capacity, no notification, no timeout job.
        $updatedKadhia = $this->entityManager->getRepository(Kadhia::class)->find($kadhia->getId());
        self::assertNotNull($updatedKadhia);
        self::assertSame(KadhiaStatus::Draft, $updatedKadhia->getStatus());
        self::assertCount(1, $updatedKadhia->getLines());

        self::assertCount(0, $this->entityManager->getRepository(Order::class)->findAll());

        $updatedSlot = $this->entityManager->getRepository(PickupSlot::class)->find($slot->getId());
        self::assertNotNull($updatedSlot);
        self::assertSame(0, $updatedSlot->getBookedCount());

        self::assertCount(0, $this->entityManager->getRepository(Notification::class)->findAll());

        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertCount(0, $transport->getSent());
    }

    public function testSubmitSlotBeyondLeadTimeSucceeds(): void
    {
        $customer = $this->createUser('lead-submit-ok@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $this->setLeadTimeMinutes($shop, 240);
        $slot = $this->createPickupSlot($shop, startsAtModifier: '+6 hours', endsAtModifier: '+7 hours');
        $product = $this->createMerchantProduct($shop, '2.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('submitted', $this->decodeJson($response)['status']);
    }

    public function testResubmissionOnSameReservedSlotSkipsLeadTime(): void
    {
        $customer = $this->createUser('lead-resubmit-same@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, startsAtModifier: '+3 hours', endsAtModifier: '+4 hours');
        $product = $this->createMerchantProduct($shop, '2.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');
        $this->createPartiallyAcceptedOrder($customer, $shop, $kadhia, $slot);

        // Policy raised after the original booking: same slot must stay allowed.
        $this->setLeadTimeMinutes($shop, 720);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('submitted', $this->decodeJson($response)['status']);
    }

    public function testResubmissionToNewTooCloseSlotIsRejected(): void
    {
        $customer = $this->createUser('lead-resubmit-newslot-ko@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $reservedSlot = $this->createPickupSlot($shop, startsAtModifier: '+3 hours', endsAtModifier: '+4 hours');
        $newSlot = $this->createPickupSlot($shop, startsAtModifier: '+4 hours', endsAtModifier: '+5 hours');
        $product = $this->createMerchantProduct($shop, '2.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');
        $order = $this->createPartiallyAcceptedOrder($customer, $shop, $kadhia, $reservedSlot);

        $this->setLeadTimeMinutes($shop, 720);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $newSlot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('PICKUP_SLOT_MINIMUM_LEAD_TIME_NOT_MET', $this->decodeJson($response)['detail']);

        $this->entityManager->clear();

        // Existing order and capacities untouched by the rejection.
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::PartiallyAccepted, $updatedOrder->getStatus());
        self::assertSame($reservedSlot->getId()->toRfc4122(), $updatedOrder->getPickupSlot()?->getId()->toRfc4122());

        $updatedNewSlot = $this->entityManager->getRepository(PickupSlot::class)->find($newSlot->getId());
        self::assertNotNull($updatedNewSlot);
        self::assertSame(0, $updatedNewSlot->getBookedCount());
    }

    public function testResubmissionToNewEligibleSlotSucceeds(): void
    {
        $customer = $this->createUser('lead-resubmit-newslot-ok@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $reservedSlot = $this->createPickupSlot($shop, startsAtModifier: '+3 hours', endsAtModifier: '+4 hours');
        $newSlot = $this->createPickupSlot($shop, startsAtModifier: '+6 hours', endsAtModifier: '+7 hours');
        $product = $this->createMerchantProduct($shop, '2.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');
        $order = $this->createPartiallyAcceptedOrder($customer, $shop, $kadhia, $reservedSlot);

        $this->setLeadTimeMinutes($shop, 240);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $newSlot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame($newSlot->getId()->toRfc4122(), $updatedOrder->getPickupSlot()?->getId()->toRfc4122());
    }

    public function testPartialAcceptanceExpirationTakesPriorityOverLeadTime(): void
    {
        $customer = $this->createUser('lead-partial-expired@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        // Reserved slot already started: the partial acceptance window is over.
        $reservedSlot = $this->createPickupSlot($shop, startsAtModifier: '-2 hours', endsAtModifier: '-1 hour');
        $newSlot = $this->createPickupSlot($shop, startsAtModifier: '+1 hour', endsAtModifier: '+2 hours');
        $product = $this->createMerchantProduct($shop, '2.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');
        $this->createPartiallyAcceptedOrder($customer, $shop, $kadhia, $reservedSlot);

        $this->setLeadTimeMinutes($shop, 720);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $newSlot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('PARTIAL_ACCEPTANCE_EXPIRED', $this->decodeJson($response)['detail']);
    }

    // Fixtures

    private function setLeadTimeMinutes(Shop $shop, int $minutes): void
    {
        $policy = (new ShopOrderingPolicy())
            ->setShop($shop)
            ->setMinimumPickupLeadTimeMinutes($minutes);
        $shop->setOrderingPolicy($policy);
        $this->entityManager->persist($policy);
        $this->entityManager->flush();
    }

    private function createPickupSlot(
        Shop $shop,
        int $capacity = 5,
        string $startsAtModifier = '+1 hour',
        string $endsAtModifier = '+2 hours',
    ): PickupSlot {
        $now = new \DateTimeImmutable();
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt(PickupSlotDisplayTime::fromPayloadInstant($now->modify($startsAtModifier)))
            ->setEndsAt(PickupSlotDisplayTime::fromPayloadInstant($now->modify($endsAtModifier)))
            ->setCapacity($capacity);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }

    private function createMerchantProduct(Shop $shop, string $priceTnd): MerchantProduct
    {
        $id = Uuid::v4();

        $brand = (new Brand())
            ->setCanonicalName('Marque Test')
            ->setSlug('marque-test-'.$id);
        $this->entityManager->persist($brand);

        $category = (new Category())
            ->setNameFr('Catégorie Test')
            ->setSlug('categorie-test-'.$id);
        $this->entityManager->persist($category);

        $ref = (new ProductReference())
            ->setNameFr('Produit Test '.$id)
            ->setBrand($brand)
            ->setCategory($category)
            ->setStatus(ProductReferenceStatus::Approved);
        $this->entityManager->persist($ref);

        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd($priceTnd)
            ->setAvailable(true)
            ->setVisible(true);
        $this->entityManager->persist($product);

        $this->entityManager->flush();

        return $product;
    }

    private function createKadhiaWithLine(
        User $customer,
        Shop $shop,
        MerchantProduct $product,
        int $quantity,
        string $unitPriceTnd,
    ): Kadhia {
        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);

        $line = (new KadhiaLine())
            ->setMerchantProduct($product)
            ->setQuantity($quantity)
            ->setUnitPriceTnd($unitPriceTnd);

        $kadhia->addLine($line);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $kadhia;
    }

    private function createPartiallyAcceptedOrder(
        User $customer,
        Shop $shop,
        Kadhia $kadhia,
        PickupSlot $slot,
    ): Order {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setKadhia($kadhia)
            ->setPickupSlot($slot);
        $this->entityManager->persist($order);
        $order->submit();
        $order->assignOrderNumber(21);
        $order->accept();
        // Force status via reflection to bypass the transition guard.
        $ref = new \ReflectionProperty(Order::class, 'status');
        $ref->setValue($order, OrderStatus::PartiallyAccepted);
        $kadhia->setStatus(KadhiaStatus::Draft);
        $this->entityManager->flush();

        return $order;
    }
}
