<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantStatisticsTopProductOutput
{
    public function __construct(
        #[SerializedName('name_fr')]
        #[Groups(['merchant_statistics:read'])]
        public string $nameFr,
        #[SerializedName('name_ar')]
        #[Groups(['merchant_statistics:read'])]
        public ?string $nameAr,
        #[SerializedName('total_quantity')]
        #[Groups(['merchant_statistics:read'])]
        public int $totalQuantity,
        #[SerializedName('total_revenue_tnd')]
        #[Groups(['merchant_statistics:read'])]
        public string $totalRevenueTnd,
    ) {
    }
}
