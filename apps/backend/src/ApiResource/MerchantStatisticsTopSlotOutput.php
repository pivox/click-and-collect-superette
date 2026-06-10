<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantStatisticsTopSlotOutput
{
    public function __construct(
        #[SerializedName('starts_at')]
        #[Groups(['merchant_statistics:read'])]
        public string $startsAt,
        #[SerializedName('ends_at')]
        #[Groups(['merchant_statistics:read'])]
        public string $endsAt,
        #[SerializedName('order_count')]
        #[Groups(['merchant_statistics:read'])]
        public int $orderCount,
    ) {
    }
}
