<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class PickupSlotOutput
{
    public function __construct(
        #[Groups(['pickup_slot:read'])]
        public string $id,
        #[Groups(['pickup_slot:read'])]
        #[SerializedName('starts_at')]
        public string $startsAt,
        #[Groups(['pickup_slot:read'])]
        #[SerializedName('ends_at')]
        public string $endsAt,
        #[Groups(['pickup_slot:read'])]
        public int $capacity,
        #[Groups(['pickup_slot:read'])]
        #[SerializedName('available_count')]
        public int $availableCount,
    ) {
    }
}
