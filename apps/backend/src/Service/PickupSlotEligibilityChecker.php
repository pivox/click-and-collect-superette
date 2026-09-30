<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PickupSlot;
use App\Entity\Shop;
use App\Repository\ShopOrderingPolicyRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Single source of truth for the minimum pickup lead time rule (ORDER-LEAD-002).
 *
 * Public slot listing and order submission both delegate here so the
 * computation is never duplicated. Convention: the server clock is authoritative,
 * comparisons happen on Africa/Tunis local time at full precision, and a slot
 * starting exactly at the limit is eligible (>=). A lead time of 0 keeps the
 * pre-existing "strictly future" behaviour enforced by the listing/submission
 * themselves and adds no extra constraint.
 */
final readonly class PickupSlotEligibilityChecker
{
    public const REJECTION_CODE = 'PICKUP_SLOT_MINIMUM_LEAD_TIME_NOT_MET';
    private const TIMEZONE = 'Africa/Tunis';

    public function __construct(
        private ShopOrderingPolicyRepository $shopOrderingPolicyRepository,
        private ClockInterface $clock,
    ) {
    }

    public function minimumPickupLeadTimeMinutes(Shop $shop): int
    {
        return $this->shopOrderingPolicyRepository->resolveMinimumPickupLeadTimeMinutes($shop);
    }

    /**
     * Theoretical earliest bookable start (serverNow + lead time), in Tunis
     * local time. Not necessarily the start of a real slot.
     */
    public function minimumEligibleStartsAt(Shop $shop, ?\DateTimeImmutable $now = null): \DateTimeImmutable
    {
        $localNow = ($now ?? $this->clock->now())->setTimezone(new \DateTimeZone(self::TIMEZONE));

        return $localNow->modify(\sprintf('+%d minutes', $this->minimumPickupLeadTimeMinutes($shop)));
    }

    /**
     * Comparison helper against a precomputed limit, so collection filtering
     * resolves the policy once instead of once per slot.
     */
    public static function isStartEligible(PickupSlot $slot, \DateTimeImmutable $minimumEligibleStartsAt): bool
    {
        return PickupSlotDisplayTime::fromStoredLocalClock($slot->getStartsAt()) >= $minimumEligibleStartsAt;
    }

    public function isEligibleForNewBooking(Shop $shop, PickupSlot $slot, ?\DateTimeImmutable $now = null): bool
    {
        if (0 === $this->minimumPickupLeadTimeMinutes($shop)) {
            return true;
        }

        return self::isStartEligible($slot, $this->minimumEligibleStartsAt($shop, $now));
    }

    public function denyUnlessEligibleForNewBooking(Shop $shop, PickupSlot $slot, ?\DateTimeImmutable $now = null): void
    {
        if (!$this->isEligibleForNewBooking($shop, $slot, $now)) {
            throw new UnprocessableEntityHttpException(self::REJECTION_CODE);
        }
    }
}
