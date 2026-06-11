<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminProductGroupReferenceOutput
{
    public function __construct(
        #[Groups(['admin_product_group:read'])]
        public string $id,
        #[Groups(['admin_product_group:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['admin_product_group:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['admin_product_group:read'])]
        #[SerializedName('brand_name')]
        public string $brandName,
        #[Groups(['admin_product_group:read'])]
        #[SerializedName('category_name_fr')]
        public string $categoryNameFr,
        #[Groups(['admin_product_group:read'])]
        public string $unit,
        #[Groups(['admin_product_group:read'])]
        public ?string $volume,
        #[Groups(['admin_product_group:read'])]
        public string $status,
    ) {
    }
}
