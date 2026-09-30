<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\PickupSlot;
use App\Entity\Shop;
use App\Repository\ShopOrderingPolicyRepository;
use App\Service\PickupSlotEligibilityChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PickupSlotEligibilityCheckerTest extends TestCase
{
    private const TUNIS = 'Africa/Tunis';

    public function testMinimumEligibleStartsAtAddsLeadTimeInTunisLocalTime(): void
    {
        $checker = $this->checkerWithLeadTime(720, '2026-10-01 10:00:00');
        $shop = new Shop();

        $limit = $checker->minimumEligibleStartsAt($shop);

        self::assertSame('2026-10-01 22:00:00', $limit->format('Y-m-d H:i:s'));
        self::assertSame(self::TUNIS, $limit->getTimezone()->getName());
    }

    public function testBrowserTimezoneNeverInfluencesTheLimit(): void
    {
        // Same instant expressed in another timezone must produce the same limit.
        $checker = $this->checkerWithLeadTime(120, '2026-10-01 10:00:00');
        $shop = new Shop();

        $parisNow = new \DateTimeImmutable('2026-10-01 10:00:00', new \DateTimeZone(self::TUNIS));
        $limit = $checker->minimumEligibleStartsAt($shop, $parisNow->setTimezone(new \DateTimeZone('Europe/Paris')));

        self::assertSame('2026-10-01 12:00:00', $limit->format('Y-m-d H:i:s'));
        self::assertSame(self::TUNIS, $limit->getTimezone()->getName());
    }

    public function testSlotExactlyAtTheLimitIsEligible(): void
    {
        $checker = $this->checkerWithLeadTime(720, '2026-10-01 10:00:00');
        $shop = new Shop();

        self::assertTrue($checker->isEligibleForNewBooking($shop, $this->slot('2026-10-01 22:00:00')));
    }

    public function testSlotOneSecondBeforeTheLimitIsNotEligible(): void
    {
        $checker = $this->checkerWithLeadTime(720, '2026-10-01 10:00:00');
        $shop = new Shop();

        self::assertFalse($checker->isEligibleForNewBooking($shop, $this->slot('2026-10-01 21:59:59')));
    }

    public function testSlotAfterTheLimitIsEligible(): void
    {
        $checker = $this->checkerWithLeadTime(720, '2026-10-01 10:00:00');
        $shop = new Shop();

        self::assertTrue($checker->isEligibleForNewBooking($shop, $this->slot('2026-10-01 22:00:01')));
    }

    public function testZeroLeadTimeAddsNoConstraint(): void
    {
        $checker = $this->checkerWithLeadTime(0, '2026-10-01 10:00:00');
        $shop = new Shop();

        // Even an already started slot passes here: the historical
        // "strictly future" filters of listing/submission keep applying.
        self::assertTrue($checker->isEligibleForNewBooking($shop, $this->slot('2026-10-01 09:00:00')));
    }

    public function testLimitCrossesMidnight(): void
    {
        $checker = $this->checkerWithLeadTime(240, '2026-10-01 23:00:00');
        $shop = new Shop();

        self::assertFalse($checker->isEligibleForNewBooking($shop, $this->slot('2026-10-02 02:00:00')));
        self::assertTrue($checker->isEligibleForNewBooking($shop, $this->slot('2026-10-02 03:00:00')));
    }

    public function testLimitCrossesMonthAndYear(): void
    {
        $checker = $this->checkerWithLeadTime(10080, '2026-12-29 12:00:00');
        $shop = new Shop();

        self::assertFalse($checker->isEligibleForNewBooking($shop, $this->slot('2027-01-05 11:00:00')));
        self::assertTrue($checker->isEligibleForNewBooking($shop, $this->slot('2027-01-05 12:00:00')));
    }

    public function testDenyThrowsStableCodeWhenNotEligible(): void
    {
        $checker = $this->checkerWithLeadTime(720, '2026-10-01 10:00:00');
        $shop = new Shop();

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('PICKUP_SLOT_MINIMUM_LEAD_TIME_NOT_MET');

        $checker->denyUnlessEligibleForNewBooking($shop, $this->slot('2026-10-01 21:00:00'));
    }

    public function testDenyPassesWhenEligible(): void
    {
        $checker = $this->checkerWithLeadTime(720, '2026-10-01 10:00:00');
        $shop = new Shop();

        $checker->denyUnlessEligibleForNewBooking($shop, $this->slot('2026-10-01 22:00:00'));

        $this->addToAssertionCount(1);
    }

    private function checkerWithLeadTime(int $minutes, string $tunisNow): PickupSlotEligibilityChecker
    {
        $repository = $this->createStub(ShopOrderingPolicyRepository::class);
        $repository->method('resolveMinimumPickupLeadTimeMinutes')->willReturn($minutes);

        return new PickupSlotEligibilityChecker(
            $repository,
            new MockClock(new \DateTimeImmutable($tunisNow, new \DateTimeZone(self::TUNIS))),
        );
    }

    private function slot(string $tunisLocalStartsAt): PickupSlot
    {
        $startsAt = new \DateTimeImmutable($tunisLocalStartsAt, new \DateTimeZone(self::TUNIS));

        return (new PickupSlot())
            ->setStartsAt($startsAt)
            ->setEndsAt($startsAt->modify('+1 hour'))
            ->setCapacity(4);
    }
}
