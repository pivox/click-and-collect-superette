<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\SubscriptionPricingPhase;

final readonly class SubscriptionPricingSnapshot
{
    public function __construct(
        public SubscriptionPricingPhase $pricingPhase,
        public string $monthlyPriceTnd,
        public \DateTimeImmutable $currentPeriodStartedAt,
        public \DateTimeImmutable $currentPeriodEndsAt,
        public ?\DateTimeImmutable $nextPhaseChangeAt,
    ) {
    }
}
