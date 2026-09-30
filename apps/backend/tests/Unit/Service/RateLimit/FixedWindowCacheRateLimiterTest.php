<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\RateLimit;

use App\Service\RateLimit\FixedWindowCacheRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

final class FixedWindowCacheRateLimiterTest extends TestCase
{
    public function testConsumeDecrementsRemainingTokensWithinTheWindow(): void
    {
        $limiter = new FixedWindowCacheRateLimiter(new ArrayAdapter(), new MockClock('2026-09-30 10:00:00'));

        $first = $limiter->consume('login_ip', '127.0.0.1', 3, 60);
        $second = $limiter->consume('login_ip', '127.0.0.1', 3, 60);

        self::assertTrue($first->accepted);
        self::assertSame(2, $first->remainingTokens);
        self::assertSame(0, $first->retryAfterSeconds);
        self::assertTrue($second->accepted);
        self::assertSame(1, $second->remainingTokens);
    }

    public function testConsumeRejectsAboveTheLimitWithCorrectRetryAfter(): void
    {
        // Window of 60s: 10:00:10 belongs to [10:00:00, 10:01:00) → 50s left.
        $limiter = new FixedWindowCacheRateLimiter(new ArrayAdapter(), new MockClock('2026-09-30 10:00:10'));

        $limiter->consume('login_ip', '127.0.0.1', 2, 60);
        $limiter->consume('login_ip', '127.0.0.1', 2, 60);
        $rejected = $limiter->consume('login_ip', '127.0.0.1', 2, 60);

        self::assertFalse($rejected->accepted);
        self::assertSame(0, $rejected->remainingTokens);
        self::assertSame(50, $rejected->retryAfterSeconds);
    }

    public function testRejectionDoesNotConsumeAToken(): void
    {
        $clock = new MockClock('2026-09-30 10:00:00');
        $limiter = new FixedWindowCacheRateLimiter(new ArrayAdapter(), $clock);

        $limiter->consume('login_ip', '127.0.0.1', 1, 60);
        $limiter->consume('login_ip', '127.0.0.1', 1, 60);
        $rejected = $limiter->consume('login_ip', '127.0.0.1', 1, 60);

        self::assertFalse($rejected->accepted);
    }

    public function testNewWindowResetsTheCounter(): void
    {
        $clock = new MockClock('2026-09-30 10:00:30');
        $limiter = new FixedWindowCacheRateLimiter(new ArrayAdapter(), $clock);

        $limiter->consume('login_ip', '127.0.0.1', 1, 60);
        $rejected = $limiter->consume('login_ip', '127.0.0.1', 1, 60);
        self::assertFalse($rejected->accepted);

        $clock->modify('+31 seconds'); // crosses into the next fixed window

        $accepted = $limiter->consume('login_ip', '127.0.0.1', 1, 60);
        self::assertTrue($accepted->accepted);
        self::assertSame(0, $accepted->remainingTokens);
    }

    public function testDistinctIdentifiersAndLimiterNamesHaveIndependentCounters(): void
    {
        $limiter = new FixedWindowCacheRateLimiter(new ArrayAdapter(), new MockClock('2026-09-30 10:00:00'));

        $limiter->consume('login_ip', '127.0.0.1', 1, 60);
        $otherIdentifier = $limiter->consume('login_ip', '10.0.0.2', 1, 60);
        $otherLimiter = $limiter->consume('refresh_ip', '127.0.0.1', 1, 60);

        self::assertTrue($otherIdentifier->accepted);
        self::assertTrue($otherLimiter->accepted);
    }

    public function testCacheKeysNeverContainTheRawIdentifier(): void
    {
        $cache = new ArrayAdapter();
        $limiter = new FixedWindowCacheRateLimiter($cache, new MockClock('2026-09-30 10:00:00'));

        $limiter->consume('login_ip_email', '192.168.1.44|client@example.tn', 5, 60);

        $keys = array_keys($cache->getValues());
        self::assertNotSame([], $keys);
        foreach ($keys as $key) {
            self::assertStringNotContainsString('client@example.tn', (string) $key);
            self::assertStringNotContainsString('192.168.1.44', (string) $key);
        }
    }
}
