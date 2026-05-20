<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

final class StorePublicApiTest extends FunctionalApiTestCase
{
    public function testGetStoreByQrTokenReturnsPublicInfo(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson('GET', \sprintf('/api/stores/by-qr/%s', $shop->getQrCodeToken()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame($shop->getName(), $payload['name']);
        self::assertSame($shop->getSlug(), $payload['slug']);
        self::assertSame($shop->getCountry(), $payload['country']);
        self::assertTrue($payload['is_active']);
        self::assertSame(\sprintf('/api/stores/%s/theme', $shop->getId()->toRfc4122()), $payload['theme_url']);
        self::assertSame(\sprintf('/api/stores/%s/catalog', $shop->getId()->toRfc4122()), $payload['catalog_url']);
    }

    public function testGetStoreByQrTokenDoesNotExposePrivateData(): void
    {
        $merchant = $this->createUser('merchant-private@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', \sprintf('/api/stores/by-qr/%s', $shop->getQrCodeToken()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayNotHasKey('owner', $payload);
        self::assertArrayNotHasKey('owner_id', $payload);
        self::assertArrayNotHasKey('email', $payload);
        self::assertArrayNotHasKey('roles', $payload);
        self::assertArrayNotHasKey('qr_code_token', $payload);
    }

    public function testGetStoreByQrTokenNotFound(): void
    {
        $response = $this->requestJson('GET', '/api/stores/by-qr/invalid-token-that-does-not-exist');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetStoreByQrTokenInactiveStoreReturns404(): void
    {
        $shop = $this->createShop(active: false);

        $response = $this->requestJson('GET', \sprintf('/api/stores/by-qr/%s', $shop->getQrCodeToken()));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetStoreByQrTokenIsPublicWithoutJwt(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson('GET', \sprintf('/api/stores/by-qr/%s', $shop->getQrCodeToken()));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testGetStoreByIdReturnsPublicInfo(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['id']);
        self::assertSame($shop->getName(), $payload['name']);
        self::assertSame($shop->getSlug(), $payload['slug']);
        self::assertSame($shop->getCountry(), $payload['country']);
        self::assertTrue($payload['is_active']);
    }

    public function testGetStoreByIdDoesNotExposePrivateData(): void
    {
        $merchant = $this->createUser('merchant-private-id@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayNotHasKey('owner', $payload);
        self::assertArrayNotHasKey('owner_id', $payload);
        self::assertArrayNotHasKey('email', $payload);
        self::assertArrayNotHasKey('roles', $payload);
        self::assertArrayNotHasKey('qr_code_token', $payload);
    }

    public function testGetStoreByIdNotFound(): void
    {
        $response = $this->requestJson('GET', '/api/stores/00000000-0000-0000-0000-000000000099');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetStoreByIdInvalidUuidReturns404(): void
    {
        $response = $this->requestJson('GET', '/api/stores/not-a-uuid');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetStoreByIdInactiveStoreReturns404(): void
    {
        $shop = $this->createShop(active: false);

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s', $shop->getId()));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetStoreByIdIsPublicWithoutJwt(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testQrEndpointAndIdEndpointReturnConsistentData(): void
    {
        $shop = $this->createShop();

        $qrResponse = $this->requestJson('GET', \sprintf('/api/stores/by-qr/%s', $shop->getQrCodeToken()));
        $idResponse = $this->requestJson('GET', \sprintf('/api/stores/%s', $shop->getId()));

        self::assertSame(200, $qrResponse->getStatusCode());
        self::assertSame(200, $idResponse->getStatusCode());

        $qrPayload = $this->decodeJson($qrResponse);
        $idPayload = $this->decodeJson($idResponse);

        self::assertSame($qrPayload['store_id'], $idPayload['id']);
        self::assertSame($qrPayload['name'], $idPayload['name']);
        self::assertSame($qrPayload['slug'], $idPayload['slug']);
        self::assertSame($qrPayload['city'], $idPayload['city']);
        self::assertSame($qrPayload['country'], $idPayload['country']);
        self::assertSame($qrPayload['is_active'], $idPayload['is_active']);
    }

    public function testPublicEndpointsExposeLogoUrlAndCoverUrl(): void
    {
        $shop = $this->createShop();
        $shop->setLogoUrl('https://cdn.example.com/logo.png');
        $shop->setCoverUrl('https://cdn.example.com/cover.jpg');
        $this->entityManager->flush();

        $qrResponse = $this->requestJson('GET', \sprintf('/api/stores/by-qr/%s', $shop->getQrCodeToken()));
        $idResponse = $this->requestJson('GET', \sprintf('/api/stores/%s', $shop->getId()));

        self::assertSame(200, $qrResponse->getStatusCode());
        self::assertSame(200, $idResponse->getStatusCode());

        $qrPayload = $this->decodeJson($qrResponse);
        self::assertSame('https://cdn.example.com/logo.png', $qrPayload['logo_url']);
        self::assertSame('https://cdn.example.com/cover.jpg', $qrPayload['cover_url']);

        $idPayload = $this->decodeJson($idResponse);
        self::assertSame('https://cdn.example.com/logo.png', $idPayload['logo_url']);
        self::assertSame('https://cdn.example.com/cover.jpg', $idPayload['cover_url']);
    }

    public function testPublicEndpointsOmitLogoUrlAndCoverUrlWhenNull(): void
    {
        $shop = $this->createShop();

        $qrResponse = $this->requestJson('GET', \sprintf('/api/stores/by-qr/%s', $shop->getQrCodeToken()));
        $idResponse = $this->requestJson('GET', \sprintf('/api/stores/%s', $shop->getId()));

        self::assertSame(200, $qrResponse->getStatusCode());
        self::assertSame(200, $idResponse->getStatusCode());

        $qrPayload = $this->decodeJson($qrResponse);
        self::assertArrayNotHasKey('logo_url', $qrPayload);
        self::assertArrayNotHasKey('cover_url', $qrPayload);

        $idPayload = $this->decodeJson($idResponse);
        self::assertArrayNotHasKey('logo_url', $idPayload);
        self::assertArrayNotHasKey('cover_url', $idPayload);
    }
}
