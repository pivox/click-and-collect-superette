<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantOrderHistoryPickupSlotOutput
{
    public function __construct(
        #[Groups(['merchant_order_history:read'])]
        #[SerializedName('starts_at')]
        public string $startsAt,
        #[Groups(['merchant_order_history:read'])]
        #[SerializedName('ends_at')]
        public string $endsAt,
    ) {
    }
}
