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
        $snapshot = $this->forSubscription($subscription, $now);

        $subscription
            ->setPricingPhase($snapshot->pricingPhase)
            ->setMonthlyPriceTnd($snapshot->monthlyPriceTnd)
            ->setCurrentPeriodStartedAt($snapshot->currentPeriodStartedAt)
            ->setCurrentPeriodEndsAt($snapshot->currentPeriodEndsAt)
            ->setNextPhaseChangeAt($snapshot->nextPhaseChangeAt);
    }

    public function forSubscription(Subscription $subscription, \DateTimeImmutable $now): SubscriptionPricingSnapshot
    {
        $terms = null;
        $scheduled = null;
        foreach ($subscription->getPricingVersions() as $version) {
            if (new \DateTimeImmutable($version['effective_at']) <= $now) {
                $terms = $version;
            } else {
                $scheduled = $version;
            }
        }
        if (null === $terms) {
            $legacy = $this->calculate($subscription->getStartedAt(), $now);

            return new SubscriptionPricingSnapshot(
                pricingPhase: $legacy->pricingPhase,
                monthlyPriceTnd: $legacy->monthlyPriceTnd,
                currentPeriodStartedAt: $legacy->currentPeriodStartedAt,
                currentPeriodEndsAt: $legacy->currentPeriodEndsAt,
                nextPhaseChangeAt: $scheduled ? new \DateTimeImmutable($scheduled['effective_at']) : $legacy->nextPhaseChangeAt,
                pricingMode: '0.000' === $legacy->monthlyPriceTnd ? 'free' : (SubscriptionPricingPhase::Promo === $legacy->pricingPhase ? 'discounted' : 'paid'),
                basePriceTnd: $legacy->monthlyPriceTnd,
                periodPriceTnd: $legacy->monthlyPriceTnd,
                scheduledPricing: $scheduled,
            );
        }

        $anchor = new \DateTimeImmutable($terms['effective_at']);
        $now = $now->setTimezone($anchor->getTimezone());
        $months = 'annual' === $terms['billing_interval'] ? 12 : 1;
        $offset = max(0, ((int) $now->format('Y') - (int) $anchor->format('Y')) * 12 + (int) $now->format('n') - (int) $anchor->format('n'));
        if ($this->monthAt($anchor, $offset) > $now) {
            $offset = max(0, $offset - 1);
        }
        $offset = intdiv($offset, $months) * $months;
        // Split in integer millimes; keep the annual remainder on the final month.
        $baseMillimes = (int) bcmul($terms['base_price_tnd'], '1000', 0);
        $part = intdiv($baseMillimes, $months);
        $total = 0;
        $totalBase = 0;
        $breakdown = [];
        for ($i = 0; $i < $months; ++$i) {
            $month = $offset + $i + 1;
            $base = $part + ($i === $months - 1 ? $baseMillimes % $months : 0);
            $discount = 0;
            if ('free' === $terms['pricing_mode'] && (null === $terms['free_months'] || $month <= $terms['free_months'])) {
                $discount = $base;
            }
            foreach ($terms['discounts'] as $rule) {
                if ($month >= $rule['from_month'] && $month <= $rule['to_month']) {
                    $discount = 'fixed' === $rule['type'] ? (int) bcmul($rule['value'], '1000', 0) : (int) bcdiv(bcmul((string) $base, $rule['value'], 3), '100', 0);
                }
            }
            $discount = min($base, $discount);
            $total += $base - $discount;
            $totalBase += $base;
            $breakdown[] = [
                'month' => $month,
                'starts_at' => $this->monthAt($anchor, $offset + $i)->format(\DATE_ATOM),
                'ends_at' => $this->monthAt($anchor, $offset + $i + 1)->format(\DATE_ATOM),
                'base_amount_tnd' => $this->amount($base),
                'discount_amount_tnd' => $this->amount($discount),
                'amount_tnd' => $this->amount($base - $discount),
            ];
        }
        $end = $this->monthAt($anchor, $offset + $months);

        return new SubscriptionPricingSnapshot(
            pricingPhase: 0 === $total ? SubscriptionPricingPhase::Trial : ($total < $totalBase ? SubscriptionPricingPhase::Promo : SubscriptionPricingPhase::Standard),
            monthlyPriceTnd: $this->amount(intdiv($total, $months)),
            currentPeriodStartedAt: $this->monthAt($anchor, $offset),
            currentPeriodEndsAt: $end,
            nextPhaseChangeAt: $end,
            billingInterval: $terms['billing_interval'],
            pricingMode: 0 === $total ? 'free' : ($total < $totalBase ? 'discounted' : 'paid'),
            basePriceTnd: $terms['base_price_tnd'],
            periodPriceTnd: $this->amount($total),
            pricingTerms: $terms,
            scheduledPricing: $scheduled,
            periodBreakdown: $breakdown,
        );
    }

    private function monthAt(\DateTimeImmutable $anchor, int $months): \DateTimeImmutable
    {
        // Recompute from the original anchor so February never shifts later renewals.
        $first = $anchor->setDate((int) $anchor->format('Y'), (int) $anchor->format('n'), 1)->modify('+'.$months.' months');

        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), min((int) $anchor->format('j'), (int) $first->format('t')));
    }

    private function amount(int $millimes): string
    {
        return bcdiv((string) $millimes, '1000', 3);
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
