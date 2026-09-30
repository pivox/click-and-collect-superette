<?php

declare(strict_types=1);

namespace App\Service\RateLimit;

/**
 * Rate limiter abstraction (#622).
 *
 * The home-grown fixed-window implementation exists because adding
 * symfony/rate-limiter was not approved (new dependency). This interface is
 * the migration seam: a future adapter can delegate to the Symfony component
 * without touching callers (pattern #9: mock the interface in tests).
 */
interface RateLimiterInterface
{
    /**
     * Attempts to consume one token for the given limiter and identifier.
     *
     * @param string $limiterName   stable policy name (used in the cache key)
     * @param string $identifier    raw identifier (IP, IP|email...); implementations
     *                              must never store it in clear in the backend
     * @param int    $limit         maximum number of requests per window
     * @param int    $windowSeconds fixed window duration in seconds
     */
    public function consume(string $limiterName, string $identifier, int $limit, int $windowSeconds): RateLimitDecision;
}
