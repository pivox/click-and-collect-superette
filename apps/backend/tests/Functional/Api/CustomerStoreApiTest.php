<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\CustomerShop;
use App\Enum\CustomerShopSource;
use App\Enum\CustomerShopStatus;

final class CustomerStoreApiTest extends FunctionalApiTestCase
{
    // POST /api/me/stores/{storeId}/visit

    public function testVisitCreatesNewRelation(): void
    {
        $customer = $this->createUser('customer-visit@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/visit', $shop->getId()),
            ['source' => 'qr_code'],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame($shop->getName(), $payload['name']);
        self::assertSame($shop->getSlug(), $payload['slug']);
        self::assertSame('TN', $payload['country']);
        self::assertTrue($payload['is_active']);
        self::assertFalse($payload['is_favorite']);
        self::assertSame('qr_code', $payload['source']);
        self::assertArrayHasKey('first_seen_at', $payload);
        self::assertArrayHasKey('last_seen_at', $payload);

        $repo = $this->entityManager->getRepository(CustomerShop::class);
        self::assertCount(1, $repo->findAll());
    }

    public function testVisitTouchesExistingRelation(): void
    {
        $customer = $this->createUser('customer-visit-update@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/visit', $shop->getId()),
            ['source' => 'qr_code'],
            $customer,
        );

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/visit', $shop->getId()),
            ['source' => 'search'],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $repo = $this->entityManager->getRepository(CustomerShop::class);
        self::assertCount(1, $repo->findAll());
    }

    public function testVisitReactivatesHiddenRelation(): void
    {
        $customer = $this->createUser('customer-reactivate@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $hidden = (new CustomerShop())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setSource(CustomerShopSource::QrCode)
            ->setStatus(CustomerShopStatus::Hidden);
        $this->entityManager->persist($hidden);
        $this->entityManager->flush();

        $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/visit', $shop->getId()),
            ['source' => 'qr_code'],
            $customer,
        );

        $listResponse = $this->requestJson('GET', '/api/me/stores', user: $customer);
        $payload = $this->decodeJson($listResponse);
        self::assertCount(1, $payload['items']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['items'][0]['store_id']);
    }

    public function testVisitShopNotFoundReturns404(): void
    {
        $customer = $this->createUser('customer-visit-404@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'POST',
            '/api/me/stores/00000000-0000-0000-0000-000000000099/visit',
            ['source' => 'qr_code'],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testVisitInactiveShopReturns404(): void
    {
        $customer = $this->createUser('customer-visit-inactive@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop(active: false);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/visit', $shop->getId()),
            ['source' => 'qr_code'],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testVisitUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/visit', $shop->getId()),
            ['source' => 'qr_code'],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testVisitWrongRoleReturns403(): void
    {
        $merchant = $this->createUser('merchant-visit-403@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/visit', $shop->getId()),
            ['source' => 'qr_code'],
            $merchant,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // GET /api/me/stores

    public function testGetStoresReturnsActiveRelations(): void
    {
        $customer = $this->createUser('customer-list@example.test', ['ROLE_CUSTOMER']);
        $shop1 = $this->createShop();
        $shop2 = $this->createShop();

        $rel1 = (new CustomerShop())->setCustomer($customer)->setShop($shop1)->setSource(CustomerShopSource::QrCode);
        $rel2 = (new CustomerShop())->setCustomer($customer)->setShop($shop2)->setSource(CustomerShopSource::Search)->setFavorite(true);

        $this->entityManager->persist($rel1);
        $this->entityManager->persist($rel2);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/me/stores', user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(2, $payload['items']);
        self::assertSame(2, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertTrue($payload['items'][0]['is_favorite']);
    }

    public function testGetStoresReturnsEmptyListForNewCustomer(): void
    {
        $customer = $this->createUser('customer-empty@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('GET', '/api/me/stores', user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame([], $payload['items']);
        self::assertSame(0, $payload['total']);
    }

    public function testGetStoresExcludesHiddenRelations(): void
    {
        $customer = $this->createUser('customer-list-hidden@example.test', ['ROLE_CUSTOMER']);
        $shop1 = $this->createShop();
        $shop2 = $this->createShop();

        $active = (new CustomerShop())->setCustomer($customer)->setShop($shop1)->setSource(CustomerShopSource::QrCode);
        $hidden = (new CustomerShop())->setCustomer($customer)->setShop($shop2)->setSource(CustomerShopSource::QrCode)->setStatus(CustomerShopStatus::Hidden);

        $this->entityManager->persist($active);
        $this->entityManager->persist($hidden);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/me/stores', user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertSame(1, $payload['total']);
    }

    public function testGetStoresPaginationReturnsCorrectSlice(): void
    {
        $customer = $this->createUser('customer-paginated@example.test', ['ROLE_CUSTOMER']);
        $shop1 = $this->createShop();
        $shop2 = $this->createShop();
        $shop3 = $this->createShop();

        foreach ([$shop1, $shop2, $shop3] as $shop) {
            $rel = (new CustomerShop())->setCustomer($customer)->setShop($shop)->setSource(CustomerShopSource::QrCode);
            $this->entityManager->persist($rel);
        }
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/me/stores?page=1&limit=2', user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(3, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(2, $payload['limit']);
        self::assertCount(2, $payload['items']);
    }

    public function testGetStoresPage2ReturnsRemainingItems(): void
    {
        $customer = $this->createUser('customer-page2@example.test', ['ROLE_CUSTOMER']);
        $shop1 = $this->createShop();
        $shop2 = $this->createShop();
        $shop3 = $this->createShop();

        foreach ([$shop1, $shop2, $shop3] as $shop) {
            $rel = (new CustomerShop())->setCustomer($customer)->setShop($shop)->setSource(CustomerShopSource::QrCode);
            $this->entityManager->persist($rel);
        }
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/me/stores?page=2&limit=2', user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(3, $payload['total']);
        self::assertSame(2, $payload['page']);
        self::assertCount(1, $payload['items']);
    }

    public function testGetStoresUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson('GET', '/api/me/stores');

        self::assertSame(401, $response->getStatusCode());
    }

    // PATCH /api/me/stores/{storeId}/favorite

    public function testSetFavoriteTrue(): void
    {
        $customer = $this->createUser('customer-fav-true@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $relation = (new CustomerShop())->setCustomer($customer)->setShop($shop)->setSource(CustomerShopSource::QrCode);
        $this->entityManager->persist($relation);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/stores/%s/favorite', $shop->getId()),
            ['is_favorite' => true],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertTrue($payload['is_favorite']);
    }

    public function testSetFavoriteFalse(): void
    {
        $customer = $this->createUser('customer-fav-false@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $relation = (new CustomerShop())->setCustomer($customer)->setShop($shop)->setSource(CustomerShopSource::QrCode)->setFavorite(true);
        $this->entityManager->persist($relation);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/stores/%s/favorite', $shop->getId()),
            ['is_favorite' => false],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertFalse($payload['is_favorite']);
    }

    public function testSetFavoriteRelationNotFoundReturns404(): void
    {
        $customer = $this->createUser('customer-fav-nrel@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/stores/%s/favorite', $shop->getId()),
            ['is_favorite' => true],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testSetFavoriteUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/stores/%s/favorite', $shop->getId()),
            ['is_favorite' => true],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    // DELETE /api/me/stores/{storeId}

    public function testHideStoreReturns204(): void
    {
        $customer = $this->createUser('customer-hide@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $relation = (new CustomerShop())->setCustomer($customer)->setShop($shop)->setSource(CustomerShopSource::QrCode);
        $this->entityManager->persist($relation);
        $this->entityManager->flush();
        $relationId = $relation->getId();

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/me/stores/%s', $shop->getId()),
            user: $customer,
        );

        self::assertSame(204, $response->getStatusCode());

        $this->entityManager->clear();
        $found = $this->entityManager->getRepository(CustomerShop::class)->find($relationId);
        self::assertNotNull($found);
        self::assertSame(CustomerShopStatus::Hidden, $found->getStatus());
    }

    public function testHideStoreRelationNotFoundReturns404(): void
    {
        $customer = $this->createUser('customer-hide-nrel@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/me/stores/%s', $shop->getId()),
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testHideStoreUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/me/stores/%s', $shop->getId()),
        );

        self::assertSame(401, $response->getStatusCode());
    }
}
