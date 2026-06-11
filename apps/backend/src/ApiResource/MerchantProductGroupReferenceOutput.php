<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantProductGroupReferenceOutput
{
    public function __construct(
        #[Groups(['merchant_product_group:read'])]
        public string $id,
        #[Groups(['merchant_product_group:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['merchant_product_group:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['merchant_product_group:read'])]
        #[SerializedName('brand_name')]
        public string $brandName,
        #[Groups(['merchant_product_group:read'])]
        #[SerializedName('category_name_fr')]
        public string $categoryNameFr,
        #[Groups(['merchant_product_group:read'])]
        public string $unit,
        #[Groups(['merchant_product_group:read'])]
        public ?string $volume,
        #[Groups(['merchant_product_group:read'])]
        public string $status,
    ) {
    }
}
