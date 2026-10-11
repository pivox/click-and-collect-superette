<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Subscription;
use App\Entity\User;
use App\Service\SubscriptionPricingSchedule;
use PHPUnit\Framework\TestCase;

final class SubscriptionCustomPricingTest extends TestCase
{
    public function testAnnualMonthlyDiscountsAndExpiry(): void
    {
        $subscription = $this->subscription('2024-01-31T10:00:00+01:00', 'annual', '600.000', 'discounted', [
            ['from_month' => 1, 'to_month' => 3, 'type' => 'percentage', 'value' => '100'],
            ['from_month' => 4, 'to_month' => 6, 'type' => 'percentage', 'value' => '50'],
        ]);
        $schedule = new SubscriptionPricingSchedule();
        $snapshot = $schedule->forSubscription($subscription, new \DateTimeImmutable('2024-02-15'));
        self::assertSame('375.000', $snapshot->periodPriceTnd);
        self::assertCount(12, $snapshot->periodBreakdown);
        self::assertStringStartsWith('2024-02-29', $snapshot->periodBreakdown[0]['ends_at']);
        self::assertStringStartsWith('2024-03-31', $snapshot->periodBreakdown[1]['ends_at']);
        self::assertSame('600.000', $schedule->forSubscription($subscription, new \DateTimeImmutable('2025-02-01'))->periodPriceTnd);
    }

    public function testAnnualRemainderPreservesExactTotalAndLeapAnchor(): void
    {
        $subscription = $this->subscription('2024-02-29T10:00:00+01:00', 'annual', '100.000');
        $schedule = new SubscriptionPricingSchedule();
        $snapshot = $schedule->forSubscription($subscription, new \DateTimeImmutable('2025-03-01'));
        self::assertSame('100.000', $snapshot->periodPriceTnd);
        self::assertSame('8.333', $snapshot->periodBreakdown[0]['base_amount_tnd']);
        self::assertSame('8.337', $snapshot->periodBreakdown[11]['base_amount_tnd']);
        self::assertStringStartsWith('2025-02-28', $snapshot->currentPeriodStartedAt->format(\DATE_ATOM));
        self::assertStringStartsWith('2028-02-29', $schedule->forSubscription($subscription, new \DateTimeImmutable('2028-03-01'))->currentPeriodStartedAt->format(\DATE_ATOM));
    }

    public function testTemporaryFreeAndFixedDiscountNeverGoNegative(): void
    {
        $subscription = $this->subscription('2026-01-31T10:00:00+01:00', 'monthly', '50.000', 'free', [], 2);
        $schedule = new SubscriptionPricingSchedule();
        self::assertSame('0.000', $schedule->forSubscription($subscription, new \DateTimeImmutable('2026-03-01'))->periodPriceTnd);
        self::assertSame('50.000', $schedule->forSubscription($subscription, new \DateTimeImmutable('2026-04-01'))->periodPriceTnd);
        self::assertSame('paid', $schedule->forSubscription($subscription, new \DateTimeImmutable('2026-04-01'))->pricingMode);
        $fixed = $this->subscription('2026-01-01', 'monthly', '50.000', 'discounted', [['from_month' => 1, 'to_month' => 1, 'type' => 'fixed', 'value' => '60']]);
        self::assertSame('0.000', $schedule->forSubscription($fixed, new \DateTimeImmutable('2026-01-15'))->periodPriceTnd);
    }

    public function testScheduledAnnualChangePreservesEarlierMonthlyTerms(): void
    {
        $subscription = $this->subscription('2026-01-31T10:00:00+01:00', 'monthly', '50.000');
        $subscription->schedulePricing(['billing_interval' => 'annual', 'pricing_mode' => 'paid', 'base_price_tnd' => '500.000', 'free_months' => null, 'discounts' => [], 'effective_at' => '2026-02-28T10:00:00+01:00'], new \DateTimeImmutable('2026-02-10'));
        $schedule = new SubscriptionPricingSchedule();
        self::assertSame('50.000', $schedule->forSubscription($subscription, new \DateTimeImmutable('2026-02-15'))->periodPriceTnd);
        $annual = $schedule->forSubscription($subscription, new \DateTimeImmutable('2026-03-01'));
        self::assertSame('500.000', $annual->periodPriceTnd);
        self::assertSame('2027-02-28T10:00:00+01:00', $annual->currentPeriodEndsAt->format(\DATE_ATOM));
    }

    public function testRenewalUsesAnchorTimezoneAcrossUtcMonthBoundary(): void
    {
        $subscription = $this->subscription('2026-01-01T00:30:00+01:00', 'monthly', '50.000');
        $snapshot = (new SubscriptionPricingSchedule())->forSubscription($subscription, new \DateTimeImmutable('2026-02-28T23:45:00+00:00'));
        self::assertSame('2026-03-01T00:30:00+01:00', $snapshot->currentPeriodStartedAt->format(\DATE_ATOM));
    }

    /** @param list<array<string, int|string>> $discounts */
    private function subscription(string $start, string $interval, string $price, string $mode = 'paid', array $discounts = [], ?int $freeMonths = null): Subscription
    {
        $date = new \DateTimeImmutable($start);
        $subscription = Subscription::startTrial(new User(), $date);
        $subscription->schedulePricing(['billing_interval' => $interval, 'pricing_mode' => $mode, 'base_price_tnd' => $price, 'free_months' => $freeMonths, 'discounts' => $discounts, 'effective_at' => $date->format(\DATE_ATOM)], $date);

        return $subscription;
    }
}
