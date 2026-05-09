<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class ProductReferenceItemOutput
{
    public function __construct(
        #[Groups(['product_reference_search:read'])]
        public string $id,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('brand_id')]
        public string $brandId,
        #[Groups(['product_reference_search:read'])]
        public string $brand,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('category_id')]
        public string $categoryId,
        #[Groups(['product_reference_search:read'])]
        public string $category,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('category_ar')]
        public ?string $categoryAr,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('category_slug')]
        public string $categorySlug,
        #[Groups(['product_reference_search:read'])]
        public ?string $volume,
        #[Groups(['product_reference_search:read'])]
        public string $unit,
        #[Groups(['product_reference_search:read'])]
        public ?string $barcode,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('already_in_catalog')]
        public bool $alreadyInCatalog,
    ) {
    }
}
