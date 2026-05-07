<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Symfony\Component\Uid\Uuid;

final class StoreThemeApiTest extends FunctionalApiTestCase
{
    public function testPublicStoreThemeFallsBackToPlatformTheme(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/theme', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame(300, $response->getMaxAge());

        $payload = $this->decodeJson($response);
        self::assertSame('#1B6CA8', $payload['--color-primary']);
        self::assertSame('Inter', $payload['--font-family']);
        self::assertSame('16px', $payload['--font-size-base']);
    }

    public function testPublicStoreThemeUsesShopThemeWhenConfigured(): void
    {
        $shop = $this->createShop();
        $this->createShopTheme($shop, [
            'primary_color' => '#AA1122',
            'secondary_color' => '#BB2233',
            'accent_color' => '#CC3344',
            'text_color' => '#111111',
            'background_color' => '#FAFAFA',
            'font_family' => 'noto_sans_arabic',
            'base_font_size' => 20,
        ]);

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/theme', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame(300, $response->getMaxAge());

        $payload = $this->decodeJson($response);
        self::assertSame('#AA1122', $payload['--color-primary']);
        self::assertSame('#BB2233', $payload['--color-secondary']);
        self::assertSame('Noto Sans Arabic', $payload['--font-family']);
        self::assertSame('20px', $payload['--font-size-base']);
    }

    public function testPublicStoreThemeReturnsNotFoundForInactiveOrUnknownShop(): void
    {
        $inactiveShop = $this->createShop(active: false);

        $inactiveResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/theme', $inactiveShop->getId()));
        $unknownResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/theme', Uuid::v4()));

        self::assertSame(404, $inactiveResponse->getStatusCode());
        self::assertSame(404, $unknownResponse->getStatusCode());
    }
}
