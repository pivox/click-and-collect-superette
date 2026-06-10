<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class ProductPackItemOutput
{
    public function __construct(
        public string $id,

        #[SerializedName('merchant_product_id')]
        public string $merchantProductId,

        #[SerializedName('name_fr')]
        public string $nameFr,

        #[SerializedName('name_ar')]
        public ?string $nameAr = null,

        public int $quantity = 1,

        #[SerializedName('unit_price_tnd')]
        public string $unitPriceTnd = '0.000',
    ) {
    }
}
