<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

final class MobileConfigApiTest extends FunctionalApiTestCase
{
    public function testPublicMobileConfigReturnsFailOpenDefaultsAnonymously(): void
    {
        $response = $this->requestJson('GET', '/api/mobile/config');

        self::assertSame(200, $response->getStatusCode());

        // Fail-open defaults: no version constraint, maintenance disabled.
        // assertSame on the full payload pins the contract structure (all four
        // app x platform combinations, stable keys, no extra field leaked).
        self::assertSame([
            'minimum_app_version' => [
                'client' => ['android' => '0.0.0', 'ios' => '0.0.0'],
                'merchant' => ['android' => '0.0.0', 'ios' => '0.0.0'],
            ],
            'maintenance' => [
                'enabled' => false,
                'message_fr' => null,
                'message_ar' => null,
            ],
        ], $this->decodeJson($response));
    }

    public function testPublicMobileConfigSendsShortPublicCacheHeader(): void
    {
        $response = $this->requestJson('GET', '/api/mobile/config');

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame(300, $response->getMaxAge());
    }
}
