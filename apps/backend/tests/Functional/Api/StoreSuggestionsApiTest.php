<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Kadhia;
use App\Entity\KadhiaLine;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\Shop;
use App\Entity\User;
use App\Tests\Functional\OrderPickupFixtureTrait;

final class StoreSuggestionsApiTest extends FunctionalApiTestCase
{
    use OrderPickupFixtureTrait;

    public function testRecentlyOrderedReturnsHistoryProducts(): void
    {
        $customer = $this->createUser('customer-sugg-recent@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);

        $this->createOrderWithProducts($customer, $shop, $product);

        $response = $this->requestJson('GET', \sprintf('/api/me/stores/%s/suggestions', $shop->getId()), user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertCount(1, $payload['recently_ordered']);
        self::assertSame($product->getId()->toRfc4122(), $payload['recently_ordered'][0]['id']);
        self::assertArrayHasKey('effective_price_tnd', $payload['recently_ordered'][0]);
        self::assertSame([], $payload['frequently_bought_together']);
    }

    public function testRecentlyOrderedExcludesUnavailableProducts(): void
    {
        $customer = $this->createUser('customer-sugg-unavailable@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);

        $this->createOrderWithProducts($customer, $shop, $product);

        $product->setAvailable(false);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/me/stores/%s/suggestions', $shop->getId()), user: $customer);

        $payload = $this->decodeJson($response);
        self::assertSame([], $payload['recently_ordered']);
    }

    public function testFrequentlyBoughtTogetherUsesKadhiaSeed(): void
    {
        $customer = $this->createUser('customer-sugg-cooc@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $productA = $this->createMerchantProduct($shop);
        $productB = $this->createMerchantProduct($shop);

        $this->createOrderWithProducts($customer, $shop, $productA, $productB);

        $kadhia = $this->createKadhiaWithProducts($customer, $shop, $productA);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/stores/%s/suggestions?kadhia_id=%s', $shop->getId(), $kadhia->getId()),
            user: $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['frequently_bought_together']);
        self::assertSame($productB->getId()->toRfc4122(), $payload['frequently_bought_together'][0]['id']);
        // Products already in the Kadhia are excluded from both sections.
        self::assertSame(
            [$productB->getId()->toRfc4122()],
            array_column($payload['recently_ordered'], 'id'),
        );
    }

    public function testKadhiaOfAnotherCustomerYieldsEmptySeed(): void
    {
        $customer = $this->createUser('customer-sugg-own@example.test', ['ROLE_CUSTOMER']);
        $other = $this->createUser('customer-sugg-other@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $productA = $this->createMerchantProduct($shop);
        $productB = $this->createMerchantProduct($shop);

        $this->createOrderWithProducts($customer, $shop, $productA, $productB);
        $otherKadhia = $this->createKadhiaWithProducts($other, $shop, $productA);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/stores/%s/suggestions?kadhia_id=%s', $shop->getId(), $otherKadhia->getId()),
            user: $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame([], $payload['frequently_bought_together']);
    }

    public function testNoHistoryReturnsEmptySections(): void
    {
        $customer = $this->createUser('customer-sugg-empty@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson('GET', \sprintf('/api/me/stores/%s/suggestions', $shop->getId()), user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame([], $payload['frequently_bought_together']);
        self::assertSame([], $payload['recently_ordered']);
    }

    public function testUnknownStoreReturns404(): void
    {
        $customer = $this->createUser('customer-sugg-404@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'GET',
            '/api/me/stores/550e8400-e29b-41d4-a716-446655440000/suggestions',
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testInvalidLimitReturns400(): void
    {
        $customer = $this->createUser('customer-sugg-limit@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/stores/%s/suggestions?limit=999', $shop->getId()),
            user: $customer,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function testMalformedKadhiaIdReturns400(): void
    {
        $customer = $this->createUser('customer-sugg-badkadhia@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/stores/%s/suggestions?kadhia_id=not-a-uuid', $shop->getId()),
            user: $customer,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function testUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson('GET', \sprintf('/api/me/stores/%s/suggestions', $shop->getId()));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testWrongRoleReturns403(): void
    {
        $merchant = $this->createUser('merchant-sugg-403@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop();

        $response = $this->requestJson('GET', \sprintf('/api/me/stores/%s/suggestions', $shop->getId()), user: $merchant);

        self::assertSame(403, $response->getStatusCode());
    }

    // Helpers

    private function createOrderWithProducts(User $customer, Shop $shop, MerchantProduct ...$products): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->submit();

        foreach ($products as $product) {
            $line = (new OrderLine())
                ->setMerchantProduct($product)
                ->setQuantity(1)
                ->setUnitPriceTnd($product->getPriceTnd())
                ->setLineTotalTnd($product->getPriceTnd());
            $order->addLine($line);
            $this->entityManager->persist($line);
        }
        $order->recomputeTotal();

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createKadhiaWithProducts(User $customer, Shop $shop, MerchantProduct ...$products): Kadhia
    {
        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        foreach ($products as $product) {
            $line = (new KadhiaLine())
                ->setMerchantProduct($product)
                ->setQuantity(1)
                ->setUnitPriceTnd($product->getPriceTnd());
            $kadhia->addLine($line);
            $this->entityManager->persist($line);
        }
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        return $kadhia;
    }
}
