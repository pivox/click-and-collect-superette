<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Booking policy block exposed additively in the public slot listing
 * (ORDER-LEAD-002). `earliest_bookable_at` is the theoretical bound
 * (serverNow + minimum lead time, Africa/Tunis), not the start of a real slot.
 */
final readonly class PickupSlotBookingPolicyOutput
{
    public function __construct(
        #[Groups(['pickup_slot:read'])]
        #[SerializedName('minimum_pickup_lead_time_minutes')]
        public int $minimumPickupLeadTimeMinutes,
        #[Groups(['pickup_slot:read'])]
        #[SerializedName('earliest_bookable_at')]
        public string $earliestBookableAt,
    ) {
    }
}
