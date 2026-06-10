<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantStatisticsTopSlotOutput
{
    public function __construct(
        #[SerializedName('starts_at')]
        public string $startsAt,
        #[SerializedName('ends_at')]
        public string $endsAt,
        #[SerializedName('order_count')]
        public int $orderCount,
    ) {
    }
}
