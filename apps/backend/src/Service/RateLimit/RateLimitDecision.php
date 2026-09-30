<?php

declare(strict_types=1);

namespace App\Service\RateLimit;

/**
 * Result of a rate limit consumption attempt (#622).
 */
final readonly class RateLimitDecision
{
    public function __construct(
        public bool $accepted,
        public int $remainingTokens,
        public int $retryAfterSeconds,
    ) {
    }
}
