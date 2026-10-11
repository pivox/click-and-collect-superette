<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\SubscriptionPricingPhase;

final readonly class SubscriptionPricingSnapshot
{
    /**
     * @param array<string, mixed>|null  $pricingTerms
     * @param array<string, mixed>|null  $scheduledPricing
     * @param list<array<string, mixed>> $periodBreakdown
     */
    public function __construct(
        public SubscriptionPricingPhase $pricingPhase,
        public string $monthlyPriceTnd,
        public \DateTimeImmutable $currentPeriodStartedAt,
        public \DateTimeImmutable $currentPeriodEndsAt,
        public ?\DateTimeImmutable $nextPhaseChangeAt,
        public string $billingInterval = 'monthly',
        public string $pricingMode = 'paid',
        public ?string $basePriceTnd = null,
        public ?string $periodPriceTnd = null,
        public ?array $pricingTerms = null,
        public ?array $scheduledPricing = null,
        public array $periodBreakdown = [],
    ) {
    }
}
