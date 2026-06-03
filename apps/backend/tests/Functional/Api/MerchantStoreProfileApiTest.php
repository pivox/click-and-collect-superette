<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Shop;

final class MerchantStoreProfileApiTest extends FunctionalApiTestCase
{
    public function testOwnerCanReadProfile(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/profile', $shop->getId()), user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['id']);
        self::assertSame($shop->getName(), $payload['name']);
        self::assertTrue($payload['active']);
    }

    public function testOwnerCanPartiallyUpdateProfile(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $shop->setPhone('+216 71 000 000');
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/profile', $shop->getId()),
            [
                'name' => 'Supérette Ezzahra Centre',
                'address' => '12 Avenue Habib Bourguiba',
                'logoUrl' => 'https://cdn.example.test/logo.png',
            ],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('Supérette Ezzahra Centre', $payload['name']);
        self::assertSame('12 Avenue Habib Bourguiba', $payload['address']);
        self::assertSame('https://cdn.example.test/logo.png', $payload['logo_url']);
        // Field absent from payload must remain untouched.
        self::assertSame('+216 71 000 000', $payload['phone']);

        $this->entityManager->clear();
        $persisted = $this->entityManager->getRepository(Shop::class)->find($shop->getId());
        self::assertInstanceOf(Shop::class, $persisted);
        self::assertSame('Supérette Ezzahra Centre', $persisted->getName());
        self::assertSame('https://cdn.example.test/logo.png', $persisted->getLogoUrl());
        self::assertSame('+216 71 000 000', $persisted->getPhone());
    }

    public function testExplicitNullClearsNullableField(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $shop->setAddress('Ancienne adresse');
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/profile', $shop->getId()),
            ['address' => null],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertNull($payload['address']);
    }

    public function testBlankNameIsRejected(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/profile', $shop->getId()),
            ['name' => '   '],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testInvalidLogoUrlIsRejected(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/profile', $shop->getId()),
            ['logoUrl' => 'ftp://cdn.example.test/logo.png'],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testNonOwnerMerchantIsDenied(): void
    {
        $owner = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/profile', $shop->getId()),
            ['name' => 'Tentative'],
            $otherMerchant,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAnonymousIsDenied(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/profile', $shop->getId()));

        self::assertContains($response->getStatusCode(), [401, 403]);
    }

    public function testUnknownStoreReturns404(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson(
            'PATCH',
            '/api/merchant/stores/550e8400-e29b-41d4-a716-446655440000/profile',
            ['name' => 'Inconnue'],
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
    }
}
