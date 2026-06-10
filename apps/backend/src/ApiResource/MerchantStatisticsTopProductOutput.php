<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantStatisticsTopProductOutput
{
    public function __construct(
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[SerializedName('total_quantity')]
        public int $totalQuantity,
        #[SerializedName('total_revenue_tnd')]
        public string $totalRevenueTnd,
    ) {
    }
}
