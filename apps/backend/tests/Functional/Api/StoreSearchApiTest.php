<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

final class StoreSearchApiTest extends FunctionalApiTestCase
{
    public function testSearchByNameReturnsMatchingActiveStores(): void
    {
        $this->createShopWithName('Supérette El Amen', 'Tunis');
        $this->createShopWithName('Supérette Ben Arous', 'Ben Arous');
        $this->createShopWithName('Épicerie du Centre', 'Tunis');

        $response = $this->requestJson('GET', '/api/stores/search?query=Amen');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertCount(1, $payload['items']);
        self::assertSame('Supérette El Amen', $payload['items'][0]['name']);
    }

    public function testSearchByCityReturnsActiveStoresInCity(): void
    {
        $this->createShopWithName('Shop Tunis 1', 'Tunis');
        $this->createShopWithName('Shop Tunis 2', 'Tunis');
        $this->createShopWithName('Shop Sfax', 'Sfax');

        $response = $this->requestJson('GET', '/api/stores/search?city=Tunis');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(2, $payload['total']);
        self::assertCount(2, $payload['items']);
    }

    public function testSearchByCityIsCaseInsensitive(): void
    {
        $this->createShopWithName('Shop Tunis', 'Tunis');

        $response = $this->requestJson('GET', '/api/stores/search?city=tunis');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
    }

    public function testSearchByQueryAndCityCombined(): void
    {
        $this->createShopWithName('Supérette El Amen', 'Tunis');
        $this->createShopWithName('Supérette El Amen Sfax', 'Sfax');

        $response = $this->requestJson('GET', '/api/stores/search?query=Amen&city=Tunis');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame('Supérette El Amen', $payload['items'][0]['name']);
    }

    public function testSearchExcludesInactiveStores(): void
    {
        $this->createShopWithName('Active Shop', 'Tunis');
        $this->createShopWithName('Inactive Shop', 'Tunis', active: false);

        $response = $this->requestJson('GET', '/api/stores/search?query=Shop');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame('Active Shop', $payload['items'][0]['name']);
    }

    public function testSearchWithNoResultsReturnsEmptyList(): void
    {
        $this->createShopWithName('Supérette El Amen', 'Tunis');

        $response = $this->requestJson('GET', '/api/stores/search?query=introuvable');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(0, $payload['total']);
        self::assertSame([], $payload['items']);
    }

    public function testSearchWithNoCriteriaReturnsEmptyList(): void
    {
        $this->createShopWithName('Supérette El Amen', 'Tunis');

        $response = $this->requestJson('GET', '/api/stores/search');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(0, $payload['total']);
        self::assertSame([], $payload['items']);
    }

    public function testSearchIsPublicWithoutJwt(): void
    {
        $response = $this->requestJson('GET', '/api/stores/search?query=test');

        self::assertSame(200, $response->getStatusCode());
    }

    public function testSearchResponseContainsExpectedFields(): void
    {
        $shop = $this->createShopWithName('Supérette El Amen', 'Tunis');

        $response = $this->requestJson('GET', '/api/stores/search?query=Amen');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        $item = $payload['items'][0];
        self::assertSame($shop->getId()->toRfc4122(), $item['store_id']);
        self::assertSame('Supérette El Amen', $item['name']);
        self::assertSame($shop->getSlug(), $item['slug']);
        self::assertSame('Tunis', $item['city']);
        self::assertSame('TN', $item['country']);
        self::assertTrue($item['is_active']);
        self::assertArrayNotHasKey('qr_code_token', $item);
        self::assertArrayNotHasKey('owner', $item);
    }

    public function testSearchByQueryMatchesCityField(): void
    {
        $this->createShopWithName('Épicerie du Nord', 'Bizerte');

        $response = $this->requestJson('GET', '/api/stores/search?query=Bizerte');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
    }

    private function createShopWithName(string $name, string $city, bool $active = true): \App\Entity\Shop
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? $name).'-'.uniqid();
        $shop = (new \App\Entity\Shop())
            ->setName($name)
            ->setSlug($slug)
            ->setCity($city)
            ->setQrCodeToken('qr-'.uniqid())
            ->setActive($active);

        $this->entityManager->persist($shop);
        $this->entityManager->flush();

        return $shop;
    }
}
