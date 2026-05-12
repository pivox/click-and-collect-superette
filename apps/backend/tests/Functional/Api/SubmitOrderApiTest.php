<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Kadhia;
use App\Entity\KadhiaLine;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\PickupSlot;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\KadhiaStatus;
use App\Enum\ProductReferenceStatus;
use Symfony\Component\Uid\Uuid;

final class SubmitOrderApiTest extends FunctionalApiTestCase
{
    // POST /api/me/stores/{storeId}/orders

    public function testSubmitOrderHappyPath(): void
    {
        $customer = $this->createUser('submit-ok@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 5);
        $product = $this->createMerchantProduct($shop, '3.000');
        $kadhia = $this->createKadhiaWithLine($customer, $shop, $product, quantity: 2, unitPriceTnd: '3.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/orders', $shop->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('submitted', $payload['status']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame($slot->getId()->toRfc4122(), $payload['pickup_slot_id']);
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
    }

    public function testSubmitOrderWithNotes(): void
    {
        $customer = $this->createUser('submit-notes@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 3);
        $product = $this->createMerchantProduct($shop, '1.500');
        $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '1.500');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/orders', $shop->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122(), 'notes' => 'Sans sel svp'],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('Sans sel svp', $payload['notes']);
    }

    public function testSubmitOrderSlotFullReturns422(): void
    {
        $customer = $this->createUser('submit-slot-full@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 1);
        $slot->book();
        $this->entityManager->flush();

        $product = $this->createMerchantProduct($shop, '2.000');
        $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/orders', $shop->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_FULL', (string) $response->getContent());
    }

    public function testSubmitOrderNoKadhiaDraftReturns422(): void
    {
        $customer = $this->createUser('submit-no-kadhia@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop, capacity: 5);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/orders', $shop->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
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
            \sprintf('/api/me/stores/%s/orders', $shop->getId()),
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
        $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/orders', $shop->getId()),
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
        $this->createKadhiaWithLine($customer, $shop, $product, quantity: 1, unitPriceTnd: '2.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/orders', $shop->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PRODUCT_UNAVAILABLE', (string) $response->getContent());
    }

    public function testSubmitOrderUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/orders', $shop->getId()),
            ['pickup_slot_id' => Uuid::v4()->toRfc4122()],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testSubmitOrderMerchantRoleReturns403(): void
    {
        $merchant = $this->createUser('submit-merchant@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/orders', $shop->getId()),
            ['pickup_slot_id' => Uuid::v4()->toRfc4122()],
            $merchant,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testSubmitOrderShopNotFoundReturns404(): void
    {
        $customer = $this->createUser('submit-no-shop@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'POST',
            '/api/me/stores/00000000-0000-0000-0000-000000000099/orders',
            ['pickup_slot_id' => Uuid::v4()->toRfc4122()],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('STORE_NOT_FOUND', (string) $response->getContent());
    }

    public function testSubmitOrderSlotFromAnotherShopReturns404(): void
    {
        $customer = $this->createUser('submit-slot-wrong-shop@example.test', ['ROLE_CUSTOMER']);
        $shop1 = $this->createShop();
        $shop2 = $this->createShop();
        $slotOtherShop = $this->createPickupSlot($shop2, capacity: 5);
        $product = $this->createMerchantProduct($shop1, '1.000');
        $this->createKadhiaWithLine($customer, $shop1, $product, quantity: 1, unitPriceTnd: '1.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/orders', $shop1->getId()),
            ['pickup_slot_id' => $slotOtherShop->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_NOT_FOUND', (string) $response->getContent());
    }

    // Helpers

    private function createPickupSlot(Shop $shop, int $capacity = 5): PickupSlot
    {
        $now = new \DateTimeImmutable();
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
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
