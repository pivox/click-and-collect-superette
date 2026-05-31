<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ExceptionalClosure;
use App\Entity\Kadhia;
use App\Entity\KadhiaLine;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderStatusLog;
use App\Entity\PickupSlot;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\KadhiaStatus;
use App\Enum\OrderStatus;
use App\Enum\ProductReferenceStatus;
use App\Message\ExpireMerchantResponseMessage;
use App\Service\PickupSlotDisplayTime;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class SubmitOrderApiTest extends FunctionalApiTestCase
{
    // POST /api/me/kadhias/{kadhiaId}/submit

    public function testSubmitOrderHappyPath(): void
    {
        $customer = $this->createUser('submit-ok@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 5);
        $product = $this->createMerchantProduct($shop, '3.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 2, unitPriceTnd: '3.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('submitted', $payload['status']);
        self::assertSame($kadhia->getId()->toRfc4122(), $payload['kadhia_id']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame($shop->getName(), $payload['store_name']);
        self::assertSame(1, $payload['order_number']);
        self::assertSame('#0001', $payload['order_number_display']);
        self::assertSame($slot->getId()->toRfc4122(), $payload['pickup_slot_id']);
        self::assertSame($slot->getId()->toRfc4122(), $payload['pickup_slot']['id']);
        self::assertSame(PickupSlotDisplayTime::toLocalAtom($slot->getStartsAt()), $payload['pickup_slot']['starts_at']);
        self::assertSame(PickupSlotDisplayTime::toLocalAtom($slot->getEndsAt()), $payload['pickup_slot']['ends_at']);
        self::assertNull($payload['notes']);
        self::assertCount(1, $payload['lines']);
        self::assertSame(2, $payload['lines'][0]['quantity']);
        self::assertSame('3.000', $payload['lines'][0]['unit_price_tnd']);
        self::assertSame('6.000', $payload['lines'][0]['line_total_tnd']);
        self::assertEqualsWithDelta(6.0, (float) $payload['total_tnd'], 0.001);

        $this->entityManager->clear();

        $updatedSlot = $this->entityManager->getRepository(PickupSlot::class)->find($slot->getId());
        self::assertNotNull($updatedSlot);
        self::assertSame(1, $updatedSlot->getBookedCount());

        $updatedKadhia = $this->entityManager->getRepository(Kadhia::class)->find($kadhia->getId());
        self::assertNotNull($updatedKadhia);
        self::assertSame(KadhiaStatus::Submitted, $updatedKadhia->getStatus());

        $orders = $this->entityManager->getRepository(Order::class)->findAll();
        self::assertCount(1, $orders);
        self::assertSame(1, $orders[0]->getOrderNumber());
        self::assertSame('#0001', $orders[0]->getOrderNumberDisplay());

        $logs = $this->entityManager->getRepository(OrderStatusLog::class)->findAll();
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::Submitted, $logs[0]->getStatus());
        self::assertNull($logs[0]->getNote());
    }

    public function testSubmitOrderAssignsSequentialOrderNumbersPerShop(): void
    {
        $customer = $this->createUser('submit-order-number-sequence@example.test', ['ROLE_CUSTOMER']);
        $shopA = $this->createShop();
        $shopB = $this->createShop();
        $slotA = $this->createPickupSlot($shopA, capacity: 5);
        $slotB = $this->createPickupSlot($shopB, capacity: 5);
        $productA = $this->createMerchantProduct($shopA, '1.000');
        $productB = $this->createMerchantProduct($shopB, '1.500');

        $firstShopAKadhia = $this->createKadhiaWithLine($customer, $shopA, $productA, quantity: 1, unitPriceTnd: '1.000');
        $secondShopAKadhia = $this->createKadhiaWithLine($customer, $shopA, $productA, quantity: 2, unitPriceTnd: '1.000');
        $firstShopBKadhia = $this->createKadhiaWithLine($customer, $shopB, $productB, quantity: 1, unitPriceTnd: '1.500');

        $firstShopAResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $firstShopAKadhia->getId()),
            ['pickup_slot_id' => $slotA->getId()->toRfc4122()],
            $customer,
        );
        $secondShopAResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $secondShopAKadhia->getId()),
            ['pickup_slot_id' => $slotA->getId()->toRfc4122()],
            $customer,
        );
        $firstShopBResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $firstShopBKadhia->getId()),
            ['pickup_slot_id' => $slotB->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(201, $firstShopAResponse->getStatusCode());
        self::assertSame(201, $secondShopAResponse->getStatusCode());
        self::assertSame(201, $firstShopBResponse->getStatusCode());

        self::assertSame(1, $this->decodeJson($firstShopAResponse)['order_number']);
        self::assertSame('#0001', $this->decodeJson($firstShopAResponse)['order_number_display']);
        self::assertSame(2, $this->decodeJson($secondShopAResponse)['order_number']);
        self::assertSame('#0002', $this->decodeJson($secondShopAResponse)['order_number_display']);
        self::assertSame(1, $this->decodeJson($firstShopBResponse)['order_number']);
        self::assertSame('#0001', $this->decodeJson($firstShopBResponse)['order_number_display']);
    }

    public function testSubmitOrderWithNotes(): void
    {
        $customer = $this->createUser('submit-notes@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 3);
        $product = $this->createMerchantProduct($shop, '1.500');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '1.500');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122(), 'notes' => 'Sans sel svp'],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('Sans sel svp', $payload['notes']);
    }

    public function testSubmitOrderSchedulesMerchantResponseTimeout(): void
    {
        $customer = $this->createUser('submit-timeout-scheduled@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 5, startsAtModifier: '+4 hours', endsAtModifier: '+5 hours');
        $product = $this->createMerchantProduct($shop, '3.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '3.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());

        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $messages = array_map(static fn ($envelope): object => $envelope->getMessage(), $transport->getSent());

        self::assertNotEmpty($messages);
        self::assertInstanceOf(ExpireMerchantResponseMessage::class, $messages[0]);
    }

    public function testSubmitOrderSlotFullReturns422(): void
    {
        $customer = $this->createUser('submit-slot-full@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 1);
        $slot->book();
        $this->entityManager->flush();

        $product = $this->createMerchantProduct($shop, '2.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_FULL', (string) $response->getContent());
    }

    public function testSubmitOrderKadhiaNotFoundReturns404(): void
    {
        $customer = $this->createUser('submit-no-kadhia@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'POST',
            '/api/me/kadhias/00000000-0000-0000-0000-000000000099/submit',
            ['pickup_slot_id' => Uuid::v4()->toRfc4122()],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('KADHIA_NOT_FOUND', (string) $response->getContent());
    }

    public function testSubmitOrderEmptyKadhiaReturns422(): void
    {
        $customer = $this->createUser('submit-empty-kadhia@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 5);

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('KADHIA_EMPTY', (string) $response->getContent());
    }

    public function testSubmitOrderProductUnavailableReturns422(): void
    {
        $customer = $this->createUser('submit-unavailable@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 5);
        $product = $this->createMerchantProduct($shop, '2.000', available: false);
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PRODUCT_UNAVAILABLE', (string) $response->getContent());
    }

    public function testSubmitOrderProductInvisibleReturns422(): void
    {
        $customer = $this->createUser('submit-invisible@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 5);
        $product = $this->createMerchantProduct($shop, '2.000', visible: false);
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PRODUCT_UNAVAILABLE', (string) $response->getContent());
    }

    public function testSubmitOrderUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson(
            'POST',
            '/api/me/kadhias/00000000-0000-0000-0000-000000000001/submit',
            ['pickup_slot_id' => Uuid::v4()->toRfc4122()],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testSubmitOrderMerchantRoleReturns403(): void
    {
        $merchant = $this->createUser('submit-merchant@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson(
            'POST',
            '/api/me/kadhias/00000000-0000-0000-0000-000000000001/submit',
            ['pickup_slot_id' => Uuid::v4()->toRfc4122()],
            $merchant,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testSubmitOrderExpiredSlotReturns422(): void
    {
        $customer = $this->createUser('submit-expired@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 5, startsAtModifier: '-3 hours', endsAtModifier: '-1 hour');
        $product = $this->createMerchantProduct($shop, '1.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '1.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_EXPIRED', (string) $response->getContent());
    }

    public function testSubmitOrderExpiredManualLocalClockSlotReturns422(): void
    {
        $customer = $this->createUser('submit-expired-local-clock@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 5, startsAtModifier: '-90 minutes', endsAtModifier: '-30 minutes');
        $product = $this->createMerchantProduct($shop, '1.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '1.000');
        $kadhiaId = $kadhia->getId()->toRfc4122();
        $slotId = $slot->getId()->toRfc4122();
        $this->entityManager->clear();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhiaId),
            ['pickup_slot_id' => $slotId],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_EXPIRED', (string) $response->getContent());
    }

    public function testSubmitOrderClosedSlotReturns422(): void
    {
        $customer = $this->createUser('submit-closed-slot@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 5);
        $closure = (new ExceptionalClosure())
            ->setShop($shop)
            ->setStartsAt($slot->getStartsAt()->modify('-15 minutes'))
            ->setEndsAt($slot->getEndsAt()->modify('+15 minutes'))
            ->setReason('Inventaire')
            ->setActive(true);
        $this->entityManager->persist($closure);
        $this->entityManager->flush();
        $product = $this->createMerchantProduct($shop, '1.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '1.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_CLOSED', (string) $response->getContent());
    }

    public function testSubmitOrderSlotFromAnotherShopReturns404(): void
    {
        $customer = $this->createUser('submit-slot-wrong-shop@example.test', ['ROLE_CUSTOMER']);
        $shop1 = $this->createShop();
        $shop2 = $this->createShop();
        $slotOtherShop = $this->createPickupSlot($shop2, capacity: 5);
        $product = $this->createMerchantProduct($shop1, '1.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop1, $product, quantity: 1, unitPriceTnd: '1.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slotOtherShop->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_NOT_FOUND', (string) $response->getContent());
    }

    public function testSubmitOrderResubmitAfterPartialAcceptanceReturns201(): void
    {
        $customer = $this->createUser('submit-resubmit@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 5, startsAtModifier: '+3 hours', endsAtModifier: '+4 hours');
        $product = $this->createMerchantProduct($shop, '2.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');

        // Simulate existing partially_accepted order linked to this kadhia
        $existingOrder = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setKadhia($kadhia)
            ->setPickupSlot($slot);
        $this->entityManager->persist($existingOrder);
        $existingOrder->submit();
        $existingOrder->assignOrderNumber(12);
        $existingOrder->accept();
        // Force status to partially_accepted via reflection to bypass transition guard
        $ref = new \ReflectionProperty(Order::class, 'status');
        $ref->setValue($existingOrder, OrderStatus::PartiallyAccepted);
        // Kadhia back to draft (as merchant would have done)
        $kadhia->setStatus(KadhiaStatus::Draft);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('submitted', $payload['status']);
        self::assertSame(12, $payload['order_number']);
        self::assertSame('#0012', $payload['order_number_display']);

        $this->entityManager->clear();

        // Must still be only one order (re-submission, not new order)
        $orders = $this->entityManager->getRepository(Order::class)->findAll();
        self::assertCount(1, $orders);

        $logs = $this->entityManager->getRepository(OrderStatusLog::class)->findAll();
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::Submitted, $logs[0]->getStatus());
        self::assertNull($logs[0]->getNote());
        self::assertSame(OrderStatus::Submitted, $orders[0]->getStatus());
        self::assertSame(12, $orders[0]->getOrderNumber());
    }

    public function testSubmitOrderResubmitAfterPartialAcceptanceDeadlineReturns422(): void
    {
        $customer = $this->createUser('submit-resubmit-expired@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 5, startsAtModifier: '+90 minutes', endsAtModifier: '+150 minutes');
        $product = $this->createMerchantProduct($shop, '2.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');

        $existingOrder = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setKadhia($kadhia)
            ->setPickupSlot($slot);
        $this->entityManager->persist($existingOrder);
        $existingOrder->submit();
        $existingOrder->partiallyAccept('Rupture');
        $kadhia->setStatus(KadhiaStatus::Draft);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PARTIAL_ACCEPTANCE_EXPIRED', (string) $response->getContent());

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($existingOrder->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::PartiallyAccepted, $updatedOrder->getStatus());
    }

    public function testPartialAcceptanceResubmissionAfterOriginalSlotDeadlineIsRejectedEvenWithLaterNewSlot(): void
    {
        $customer = $this->createUser('submit-resubmit-original-deadline@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $originalSlot = $this->createPickupSlot($shop, capacity: 5, startsAtModifier: '+90 minutes', endsAtModifier: '+150 minutes');
        $newLaterSlot = $this->createPickupSlot($shop, capacity: 5, startsAtModifier: '+5 hours', endsAtModifier: '+6 hours');
        $product = $this->createMerchantProduct($shop, '2.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');

        $originalSlot->book();
        $existingOrder = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setKadhia($kadhia)
            ->setPickupSlot($originalSlot);
        $this->entityManager->persist($existingOrder);
        $existingOrder->submit();
        $existingOrder->partiallyAccept('Rupture');
        $kadhia->setStatus(KadhiaStatus::Draft);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $newLaterSlot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PARTIAL_ACCEPTANCE_EXPIRED', (string) $response->getContent());

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($existingOrder->getId());
        $updatedOriginalSlot = $this->entityManager->getRepository(PickupSlot::class)->find($originalSlot->getId());
        $updatedNewSlot = $this->entityManager->getRepository(PickupSlot::class)->find($newLaterSlot->getId());

        self::assertNotNull($updatedOrder);
        self::assertNotNull($updatedOriginalSlot);
        self::assertNotNull($updatedNewSlot);
        self::assertSame(OrderStatus::PartiallyAccepted, $updatedOrder->getStatus());
        self::assertSame($originalSlot->getId()->toRfc4122(), $updatedOrder->getPickupSlot()?->getId()->toRfc4122());
        self::assertSame(1, $updatedOriginalSlot->getBookedCount());
        self::assertSame(0, $updatedNewSlot->getBookedCount());
    }

    // Helpers

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

    private function createMerchantProduct(
        Shop $shop,
        string $priceTnd,
        bool $available = true,
        bool $visible = true,
    ): MerchantProduct {
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
            ->setAvailable($available)
            ->setVisible($visible);
        $this->entityManager->persist($product);

        $this->entityManager->flush();

        return $product;
    }

    public function testSubmitAlreadySubmittedKadhiaReturnsExistingOrder(): void
    {
        $customer = $this->createUser('submit-idempotent@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 5);
        $product = $this->createMerchantProduct($shop, '2.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');

        // First submit
        $first = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );
        self::assertSame(201, $first->getStatusCode());
        $firstPayload = $this->decodeJson($first);
        $firstOrderId = $firstPayload['id'];

        // Second submit — same Kadhia, same slot
        $second = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );
        self::assertSame(201, $second->getStatusCode());
        $secondPayload = $this->decodeJson($second);

        // Same order returned — no duplicate created
        self::assertSame($firstOrderId, $secondPayload['id']);
        self::assertSame($firstPayload['order_number'], $secondPayload['order_number']);
        self::assertSame($firstPayload['order_number_display'], $secondPayload['order_number_display']);
        $this->entityManager->clear();
        self::assertCount(1, $this->entityManager->getRepository(Order::class)->findAll());

        // Slot booked count was NOT incremented a second time
        $updatedSlot = $this->entityManager->getRepository(PickupSlot::class)->find($slot->getId());
        self::assertNotNull($updatedSlot);
        self::assertSame(1, $updatedSlot->getBookedCount());
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
}
