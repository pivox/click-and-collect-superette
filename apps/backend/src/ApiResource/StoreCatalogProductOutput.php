<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class StoreCatalogProductOutput
{
    public function __construct(
        #[Groups(['store_catalog:read'])]
        public string $id,
        #[Groups(['store_catalog:read'])]
        #[SerializedName('product_reference_id')]
        public ?string $productReferenceId,
        #[Groups(['store_catalog:read'])]
        #[SerializedName('local_product_id')]
        public ?string $localProductId,
        #[Groups(['store_catalog:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['store_catalog:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['store_catalog:read'])]
        public ?string $brand,
        #[Groups(['store_catalog:read'])]
        public string $category,
        #[Groups(['store_catalog:read'])]
        #[SerializedName('category_ar')]
        public ?string $categoryAr,
        #[Groups(['store_catalog:read'])]
        #[SerializedName('category_slug')]
        public string $categorySlug,
        #[Groups(['store_catalog:read'])]
        public ?string $volume,
        #[Groups(['store_catalog:read'])]
        public string $unit,
        #[Groups(['store_catalog:read'])]
        #[SerializedName('price_tnd')]
        public string $priceTnd,
        #[Groups(['store_catalog:read'])]
        #[SerializedName('is_available')]
        public bool $isAvailable,
    ) {
    }
}
