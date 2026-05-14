<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantDashboardPickupSlotOutput
{
    public function __construct(
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('pickup_slot_id')]
        public string $pickupSlotId,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('starts_at')]
        public string $startsAt,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('ends_at')]
        public string $endsAt,
        #[Groups(['merchant_dashboard:read'])]
        public int $capacity,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('booked_count')]
        public int $bookedCount,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('remaining_capacity')]
        public int $remainingCapacity,
    ) {
    }
}
