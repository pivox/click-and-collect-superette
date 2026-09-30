<?php

declare(strict_types=1);

namespace App\Service\RateLimit;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;

/**
 * Fixed-window rate limiter backed by the existing application cache (#622).
 *
 * No new dependency or infrastructure: counters live in the PSR-6 `cache.app`
 * pool. The cache key never contains the raw identifier (IP, email) — only a
 * sha256 hash — so no personal data ends up in clear in the cache backend.
 */
final readonly class FixedWindowCacheRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private ClockInterface $clock,
    ) {
    }

    public function consume(string $limiterName, string $identifier, int $limit, int $windowSeconds): RateLimitDecision
    {
        $now = $this->clock->now()->getTimestamp();
        $window = intdiv($now, $windowSeconds);
        $windowEndsAt = ($window + 1) * $windowSeconds;

        $key = \sprintf('rate_limit.%s.%d.%s', $limiterName, $window, hash('sha256', $identifier));

        $item = $this->cache->getItem($key);
        $count = $item->get();
        if (!\is_int($count) || $count < 0) {
            $count = 0;
        }

        if ($count >= $limit) {
            return new RateLimitDecision(
                accepted: false,
                remainingTokens: 0,
                retryAfterSeconds: max(1, $windowEndsAt - $now),
            );
        }

        ++$count;
        $item->set($count);
        $item->expiresAfter($windowSeconds);
        $this->cache->save($item);

        return new RateLimitDecision(
            accepted: true,
            remainingTokens: $limit - $count,
            retryAfterSeconds: 0,
        );
    }
}
