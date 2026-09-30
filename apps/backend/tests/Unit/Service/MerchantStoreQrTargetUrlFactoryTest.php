<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Shop;
use App\Service\MerchantStoreQrTargetUrlFactory;
use PHPUnit\Framework\TestCase;

/**
 * #543: the QR target URL must stay a deterministic absolute URL built from
 * FRONTEND_URL, whatever local override is in place (trailing slash included).
 */
final class MerchantStoreQrTargetUrlFactoryTest extends TestCase
{
    public function testBuildsAbsoluteUrlFromFrontendUrl(): void
    {
        $factory = new MerchantStoreQrTargetUrlFactory('http://localhost:3000');

        self::assertSame(
            'http://localhost:3000/stores/by-qr/token-123',
            $factory->create($this->shop('token-123')),
        );
    }

    public function testTrailingSlashInFrontendUrlIsNormalized(): void
    {
        $factory = new MerchantStoreQrTargetUrlFactory('https://kadhia.example.tn/');

        self::assertSame(
            'https://kadhia.example.tn/stores/by-qr/token-123',
            $factory->create($this->shop('token-123')),
        );
    }

    public function testQrTokenIsUrlEncoded(): void
    {
        $factory = new MerchantStoreQrTargetUrlFactory('http://localhost:3000');

        self::assertSame(
            'http://localhost:3000/stores/by-qr/a%2Fb%20c',
            $factory->create($this->shop('a/b c')),
        );
    }

    public function testLanOverrideProducesThatOrigin(): void
    {
        // Local LAN overrides (mobile testing) flow through untouched; tests
        // remain deterministic because phpunit.dist.xml forces FRONTEND_URL.
        $factory = new MerchantStoreQrTargetUrlFactory('http://192.168.1.48:3000');

        self::assertSame(
            'http://192.168.1.48:3000/stores/by-qr/token-123',
            $factory->create($this->shop('token-123')),
        );
    }

    private function shop(string $qrCodeToken): Shop
    {
        return (new Shop())
            ->setName('Supérette Test')
            ->setSlug('superette-test')
            ->setQrCodeToken($qrCodeToken);
    }
}
