<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Subscription;
use App\Enum\SubscriptionPricingPhase;

final readonly class SubscriptionPricingSchedule
{
    public function calculate(\DateTimeImmutable $startedAt, \DateTimeImmutable $now): SubscriptionPricingSnapshot
    {
        $trialEndsAt = $startedAt->modify('+3 months');
        $promoEndsAt = $startedAt->modify('+6 months');

        if ($now < $trialEndsAt) {
            $phase = SubscriptionPricingPhase::Trial;
            $price = '0.000';
            $nextPhaseChangeAt = $trialEndsAt;
        } elseif ($now < $promoEndsAt) {
            $phase = SubscriptionPricingPhase::Promo;
            $price = '10.000';
            $nextPhaseChangeAt = $promoEndsAt;
        } else {
            $phase = SubscriptionPricingPhase::Standard;
            $price = '50.000';
            $nextPhaseChangeAt = null;
        }

        $periodStartedAt = $this->periodStartedAt($startedAt, $now);

        return new SubscriptionPricingSnapshot(
            pricingPhase: $phase,
            monthlyPriceTnd: $price,
            currentPeriodStartedAt: $periodStartedAt,
            currentPeriodEndsAt: $periodStartedAt->modify('+1 month'),
            nextPhaseChangeAt: $nextPhaseChangeAt,
        );
    }

    public function refresh(Subscription $subscription, \DateTimeImmutable $now): void
    {
        $snapshot = $this->calculate($subscription->getStartedAt(), $now);

        $subscription
            ->setPricingPhase($snapshot->pricingPhase)
            ->setMonthlyPriceTnd($snapshot->monthlyPriceTnd)
            ->setCurrentPeriodStartedAt($snapshot->currentPeriodStartedAt)
            ->setCurrentPeriodEndsAt($snapshot->currentPeriodEndsAt)
            ->setNextPhaseChangeAt($snapshot->nextPhaseChangeAt);
    }

    private function periodStartedAt(\DateTimeImmutable $startedAt, \DateTimeImmutable $now): \DateTimeImmutable
    {
        if ($now <= $startedAt) {
            return $startedAt;
        }

        $periodStartedAt = $startedAt;
        while ($periodStartedAt->modify('+1 month') <= $now) {
            $periodStartedAt = $periodStartedAt->modify('+1 month');
        }

        return $periodStartedAt;
    }
}
