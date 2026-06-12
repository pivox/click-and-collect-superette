<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\CustomerProductFavorite;
use App\Tests\Functional\OrderPickupFixtureTrait;

final class CustomerProductFavoriteApiTest extends FunctionalApiTestCase
{
    use OrderPickupFixtureTrait;

    // PATCH /api/me/products/{merchantProductId}/favorite

    public function testMarkFavoriteCreatesRelation(): void
    {
        $customer = $this->createUser('customer-fav@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/products/%s/favorite', $product->getId()),
            ['is_favorite' => true],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($product->getId()->toRfc4122(), $payload['merchant_product_id']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertTrue($payload['is_favorite']);

        $repo = $this->entityManager->getRepository(CustomerProductFavorite::class);
        self::assertCount(1, $repo->findAll());
    }

    public function testMarkFavoriteTwiceIsIdempotent(): void
    {
        $customer = $this->createUser('customer-fav-twice@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);

        $this->requestJson('PATCH', \sprintf('/api/me/products/%s/favorite', $product->getId()), ['is_favorite' => true], $customer);
        $response = $this->requestJson('PATCH', \sprintf('/api/me/products/%s/favorite', $product->getId()), ['is_favorite' => true], $customer);

        self::assertSame(200, $response->getStatusCode());
        $repo = $this->entityManager->getRepository(CustomerProductFavorite::class);
        self::assertCount(1, $repo->findAll());
    }

    public function testUnmarkFavoriteRemovesRelation(): void
    {
        $customer = $this->createUser('customer-unfav@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);

        $this->requestJson('PATCH', \sprintf('/api/me/products/%s/favorite', $product->getId()), ['is_favorite' => true], $customer);
        $response = $this->requestJson('PATCH', \sprintf('/api/me/products/%s/favorite', $product->getId()), ['is_favorite' => false], $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertFalse($payload['is_favorite']);

        $repo = $this->entityManager->getRepository(CustomerProductFavorite::class);
        self::assertCount(0, $repo->findAll());
    }

    public function testUnmarkWithoutExistingFavoriteIsIdempotent(): void
    {
        $customer = $this->createUser('customer-unfav-none@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/products/%s/favorite', $product->getId()),
            ['is_favorite' => false],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testFavoriteUnknownProductReturns404(): void
    {
        $customer = $this->createUser('customer-fav-404@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'PATCH',
            '/api/me/products/550e8400-e29b-41d4-a716-446655440000/favorite',
            ['is_favorite' => true],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testFavoriteHiddenProductReturns404(): void
    {
        $customer = $this->createUser('customer-fav-hidden@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);
        $product->setVisible(false);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/products/%s/favorite', $product->getId()),
            ['is_favorite' => true],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testFavoriteUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/products/%s/favorite', $product->getId()),
            ['is_favorite' => true],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testFavoriteWrongRoleReturns403(): void
    {
        $merchant = $this->createUser('merchant-fav-403@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/products/%s/favorite', $product->getId()),
            ['is_favorite' => true],
            $merchant,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // GET /api/me/stores/{storeId}/favorite-products

    public function testFavoriteProductsListIsScopedByStore(): void
    {
        $customer = $this->createUser('customer-fav-list@example.test', ['ROLE_CUSTOMER']);
        $shopA = $this->createShop();
        $shopB = $this->createShop();
        $productA = $this->createMerchantProduct($shopA);
        $productB = $this->createMerchantProduct($shopB);

        $this->requestJson('PATCH', \sprintf('/api/me/products/%s/favorite', $productA->getId()), ['is_favorite' => true], $customer);
        $this->requestJson('PATCH', \sprintf('/api/me/products/%s/favorite', $productB->getId()), ['is_favorite' => true], $customer);

        $response = $this->requestJson('GET', \sprintf('/api/me/stores/%s/favorite-products', $shopA->getId()), user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shopA->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame(1, $payload['total']);
        self::assertCount(1, $payload['items']);
        self::assertSame($productA->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('Produit test', $payload['items'][0]['name_fr']);
        self::assertArrayHasKey('effective_price_tnd', $payload['items'][0]);
    }

    public function testFavoriteProductsListIsScopedByCustomer(): void
    {
        $customer = $this->createUser('customer-fav-own@example.test', ['ROLE_CUSTOMER']);
        $other = $this->createUser('customer-fav-other@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);

        $this->requestJson('PATCH', \sprintf('/api/me/products/%s/favorite', $product->getId()), ['is_favorite' => true], $other);

        $response = $this->requestJson('GET', \sprintf('/api/me/stores/%s/favorite-products', $shop->getId()), user: $customer);

        $payload = $this->decodeJson($response);
        self::assertSame(0, $payload['total']);
        self::assertCount(0, $payload['items']);
    }

    public function testFavoriteProductsListKeepsUnavailableProducts(): void
    {
        $customer = $this->createUser('customer-fav-unavailable@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);

        $this->requestJson('PATCH', \sprintf('/api/me/products/%s/favorite', $product->getId()), ['is_favorite' => true], $customer);

        $product->setAvailable(false);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/me/stores/%s/favorite-products', $shop->getId()), user: $customer);

        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertFalse($payload['items'][0]['is_available']);
    }

    public function testFavoriteProductsListExcludesHiddenProducts(): void
    {
        $customer = $this->createUser('customer-fav-hidden-list@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);

        $this->requestJson('PATCH', \sprintf('/api/me/products/%s/favorite', $product->getId()), ['is_favorite' => true], $customer);

        $product->setVisible(false);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/me/stores/%s/favorite-products', $shop->getId()), user: $customer);

        $payload = $this->decodeJson($response);
        self::assertSame(0, $payload['total']);
    }

    public function testFavoriteProductsListExcludesNonApprovedReferences(): void
    {
        $customer = $this->createUser('customer-fav-unapproved@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);

        $this->requestJson('PATCH', \sprintf('/api/me/products/%s/favorite', $product->getId()), ['is_favorite' => true], $customer);

        // Reference moved out of the approved catalog after the favorite was set.
        $product->getProductReference()?->setStatus(\App\Enum\ProductReferenceStatus::Archived);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/me/stores/%s/favorite-products', $shop->getId()), user: $customer);

        $payload = $this->decodeJson($response);
        self::assertSame(0, $payload['total']);
    }

    public function testFavoriteProductsListExcludesZeroPricedProducts(): void
    {
        $customer = $this->createUser('customer-fav-zeroprice@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);

        $this->requestJson('PATCH', \sprintf('/api/me/products/%s/favorite', $product->getId()), ['is_favorite' => true], $customer);

        $product->setPriceTnd('0.000');
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/me/stores/%s/favorite-products', $shop->getId()), user: $customer);

        $payload = $this->decodeJson($response);
        self::assertSame(0, $payload['total']);
    }

    public function testFavoriteProductsListUnknownStoreReturns404(): void
    {
        $customer = $this->createUser('customer-fav-list-404@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'GET',
            '/api/me/stores/550e8400-e29b-41d4-a716-446655440000/favorite-products',
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testFavoriteProductsListUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson('GET', \sprintf('/api/me/stores/%s/favorite-products', $shop->getId()));

        self::assertSame(401, $response->getStatusCode());
    }
}
