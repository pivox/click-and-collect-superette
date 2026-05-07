<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\ShopTheme;

final class MerchantShopThemeApiTest extends FunctionalApiTestCase
{
    public function testOwnerMerchantCanReadFallbackThemeAndUpsertShopTheme(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $getResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/theme', $shop->getId()), user: $merchant);

        self::assertSame(200, $getResponse->getStatusCode());
        $getPayload = $this->decodeJson($getResponse);
        self::assertSame('#1B6CA8', $getPayload['primary_color']);
        self::assertArrayHasKey('warnings', $getPayload);
        self::assertSame([], $getPayload['warnings']);

        $putResponse = $this->requestJson(
            'PUT',
            \sprintf('/api/merchant/stores/%s/theme', $shop->getId()),
            $this->validThemePayload(['primary_color' => '#AA1122']),
            $merchant,
        );

        self::assertSame(200, $putResponse->getStatusCode());
        $putPayload = $this->decodeJson($putResponse);
        self::assertSame('#AA1122', $putPayload['primary_color']);
        self::assertSame('cairo', $putPayload['font_family']);

        $shopTheme = $this->entityManager->getRepository(ShopTheme::class)->findOneBy(['shop' => $shop]);
        self::assertInstanceOf(ShopTheme::class, $shopTheme);
        self::assertSame('#AA1122', $shopTheme->getPrimaryColor());

        $secondPutResponse = $this->requestJson(
            'PUT',
            \sprintf('/api/merchant/stores/%s/theme', $shop->getId()),
            $this->validThemePayload(['primary_color' => '#BB2233', 'base_font_size' => 19]),
            $merchant,
        );

        self::assertSame(200, $secondPutResponse->getStatusCode());
        self::assertSame(1, $this->entityManager->getRepository(ShopTheme::class)->count(['shop' => $shop]));
        $updatedShopTheme = $this->entityManager->getRepository(ShopTheme::class)->findOneBy(['shop' => $shop]);
        self::assertInstanceOf(ShopTheme::class, $updatedShopTheme);
        self::assertSame('#BB2233', $updatedShopTheme->getPrimaryColor());
        self::assertSame(19, $updatedShopTheme->getBaseFontSize());
    }

    public function testNonOwnerMerchantIsDenied(): void
    {
        $owner = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);

        $getResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/theme', $shop->getId()), user: $otherMerchant);
        $putResponse = $this->requestJson(
            'PUT',
            \sprintf('/api/merchant/stores/%s/theme', $shop->getId()),
            $this->validThemePayload(),
            $otherMerchant,
        );

        self::assertSame(403, $getResponse->getStatusCode());
        self::assertSame(403, $putResponse->getStatusCode());
    }

    public function testAnonymousUserIsDeniedOnMerchantThemeRoutes(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $getResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/theme', $shop->getId()));
        $putResponse = $this->requestJson(
            'PUT',
            \sprintf('/api/merchant/stores/%s/theme', $shop->getId()),
            $this->validThemePayload(),
        );

        // API Platform exception listener (priority 0) may intercept AccessDeniedException
        // before Symfony security's entry point (priority -64), so both 401 and 403 are valid.
        self::assertContains($getResponse->getStatusCode(), [401, 403]);
        self::assertContains($putResponse->getStatusCode(), [401, 403]);
    }

    public function testSimpleClientIsDeniedOnMerchantThemeRoutes(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $client = $this->createUser('client@example.test');
        $shop = $this->createShop($merchant);

        $getResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/theme', $shop->getId()), user: $client);
        $putResponse = $this->requestJson(
            'PUT',
            \sprintf('/api/merchant/stores/%s/theme', $shop->getId()),
            $this->validThemePayload(),
            $client,
        );

        self::assertSame(403, $getResponse->getStatusCode());
        self::assertSame(403, $putResponse->getStatusCode());
    }
}
