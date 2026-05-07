<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\PlatformTheme;

final class PlatformThemeApiTest extends FunctionalApiTestCase
{
    public function testAdminCanReadAndUpdatePlatformTheme(): void
    {
        $admin = $this->createUser('admin@example.test', ['ROLE_ADMIN']);

        $getResponse = $this->requestJson('GET', '/api/admin/theme', user: $admin);

        self::assertSame(200, $getResponse->getStatusCode());
        $getPayload = $this->decodeJson($getResponse);
        self::assertSame('#1B6CA8', $getPayload['primary_color']);

        $putResponse = $this->requestJson(
            'PUT',
            '/api/admin/theme',
            $this->validThemePayload(['primary_color' => '#0A7A4B', 'font_family' => 'roboto']),
            $admin,
        );

        self::assertSame(200, $putResponse->getStatusCode());
        $putPayload = $this->decodeJson($putResponse);
        self::assertSame('#0A7A4B', $putPayload['primary_color']);
        self::assertSame('roboto', $putPayload['font_family']);

        $platformTheme = $this->entityManager->getRepository(PlatformTheme::class)->find(PlatformTheme::DEFAULT_ID);
        self::assertInstanceOf(PlatformTheme::class, $platformTheme);
        self::assertSame('#0A7A4B', $platformTheme->getPrimaryColor());
    }

    public function testAdminThemeRoutesDenyUsersWithoutAdminRole(): void
    {
        $merchant = $this->createUser('merchant@example.test', ['ROLE_MERCHANT']);

        $getResponse = $this->requestJson('GET', '/api/admin/theme', user: $merchant);
        $putResponse = $this->requestJson('PUT', '/api/admin/theme', $this->validThemePayload(), $merchant);

        self::assertSame(403, $getResponse->getStatusCode());
        self::assertSame(403, $putResponse->getStatusCode());
    }

    public function testAdminThemeRoutesDenyAnonymousUsers(): void
    {
        $getResponse = $this->requestJson('GET', '/api/admin/theme');
        $putResponse = $this->requestJson('PUT', '/api/admin/theme', $this->validThemePayload());

        self::assertContains($getResponse->getStatusCode(), [401, 403]);
        self::assertContains($putResponse->getStatusCode(), [401, 403]);
    }
}
