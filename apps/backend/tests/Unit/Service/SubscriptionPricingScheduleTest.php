<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionLifecycle;
use App\Enum\SubscriptionPricingPhase;
use App\Service\SubscriptionPricingSchedule;
use PHPUnit\Framework\TestCase;

final class SubscriptionPricingScheduleTest extends TestCase
{
    public function testTrialPhaseCoversTheFirstThreeMonthsForFree(): void
    {
        $schedule = new SubscriptionPricingSchedule();
        $startedAt = new \DateTimeImmutable('2026-06-01T00:00:00+01:00');

        $snapshot = $schedule->calculate($startedAt, new \DateTimeImmutable('2026-08-31T12:00:00+01:00'));

        self::assertSame(SubscriptionPricingPhase::Trial, $snapshot->pricingPhase);
        self::assertSame('0.000', $snapshot->monthlyPriceTnd);
        self::assertSame('2026-08-01T00:00:00+01:00', $snapshot->currentPeriodStartedAt->format(\DateTimeInterface::ATOM));
        self::assertSame('2026-09-01T00:00:00+01:00', $snapshot->currentPeriodEndsAt->format(\DateTimeInterface::ATOM));
        self::assertSame('2026-09-01T00:00:00+01:00', $snapshot->nextPhaseChangeAt?->format(\DateTimeInterface::ATOM));
    }

    public function testPromoPhaseCoversMonthsFourToSixAtTenTnd(): void
    {
        $schedule = new SubscriptionPricingSchedule();
        $startedAt = new \DateTimeImmutable('2026-06-01T00:00:00+01:00');

        $snapshot = $schedule->calculate($startedAt, new \DateTimeImmutable('2026-09-01T00:00:00+01:00'));

        self::assertSame(SubscriptionPricingPhase::Promo, $snapshot->pricingPhase);
        self::assertSame('10.000', $snapshot->monthlyPriceTnd);
        self::assertSame('2026-09-01T00:00:00+01:00', $snapshot->currentPeriodStartedAt->format(\DateTimeInterface::ATOM));
        self::assertSame('2026-10-01T00:00:00+01:00', $snapshot->currentPeriodEndsAt->format(\DateTimeInterface::ATOM));
        self::assertSame('2026-12-01T00:00:00+01:00', $snapshot->nextPhaseChangeAt?->format(\DateTimeInterface::ATOM));
    }

    public function testStandardPhaseStartsAfterSixMonthsAtFiftyTnd(): void
    {
        $schedule = new SubscriptionPricingSchedule();
        $startedAt = new \DateTimeImmutable('2026-06-01T00:00:00+01:00');

        $snapshot = $schedule->calculate($startedAt, new \DateTimeImmutable('2026-12-01T00:00:00+01:00'));

        self::assertSame(SubscriptionPricingPhase::Standard, $snapshot->pricingPhase);
        self::assertSame('50.000', $snapshot->monthlyPriceTnd);
        self::assertSame('2026-12-01T00:00:00+01:00', $snapshot->currentPeriodStartedAt->format(\DateTimeInterface::ATOM));
        self::assertSame('2027-01-01T00:00:00+01:00', $snapshot->currentPeriodEndsAt->format(\DateTimeInterface::ATOM));
        self::assertNull($snapshot->nextPhaseChangeAt);
    }

    public function testRefreshingPricingDoesNotChangeLifecycle(): void
    {
        $merchant = (new User())
            ->setEmail('merchant-subscription@example.test')
            ->setRoles(['ROLE_MERCHANT'])
            ->setPassword('test-password')
            ->setName('Marchand Test');
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $subscription->setLifecycle(SubscriptionLifecycle::Suspended);

        (new SubscriptionPricingSchedule())->refresh($subscription, new \DateTimeImmutable('2026-12-01T00:00:00+01:00'));

        self::assertSame(SubscriptionLifecycle::Suspended, $subscription->getLifecycle());
        self::assertSame(SubscriptionPricingPhase::Standard, $subscription->getPricingPhase());
        self::assertSame('50.000', $subscription->getMonthlyPriceTnd());
    }
}
