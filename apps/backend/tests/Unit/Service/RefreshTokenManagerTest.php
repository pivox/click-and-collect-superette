<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\RefreshTokenManager;
use PHPUnit\Framework\TestCase;

final class RefreshTokenManagerTest extends TestCase
{
    public function testGenerateRawTokenIsBase64UrlWith256BitsOfEntropy(): void
    {
        $rawToken = RefreshTokenManager::generateRawToken();

        // 32 random bytes → 43 base64url chars without padding.
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $rawToken);
    }

    public function testGenerateRawTokenIsUniqueAcrossCalls(): void
    {
        $tokens = [];
        for ($i = 0; $i < 100; ++$i) {
            $tokens[] = RefreshTokenManager::generateRawToken();
        }

        self::assertCount(100, array_unique($tokens));
    }

    public function testHashTokenIsSha256AndDeterministic(): void
    {
        $rawToken = 'some-raw-refresh-token';

        self::assertSame(hash('sha256', $rawToken), RefreshTokenManager::hashToken($rawToken));
        self::assertSame(RefreshTokenManager::hashToken($rawToken), RefreshTokenManager::hashToken($rawToken));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', RefreshTokenManager::hashToken($rawToken));
        self::assertNotSame($rawToken, RefreshTokenManager::hashToken($rawToken));
    }

    public function testNormalizeDeviceLabelTrimsAndTruncates(): void
    {
        self::assertNull(RefreshTokenManager::normalizeDeviceLabel(null));
        self::assertNull(RefreshTokenManager::normalizeDeviceLabel('   '));
        self::assertSame('Pixel 7', RefreshTokenManager::normalizeDeviceLabel('  Pixel 7  '));
        self::assertSame(
            str_repeat('a', 120),
            RefreshTokenManager::normalizeDeviceLabel(str_repeat('a', 200)),
        );
    }
}
