<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantCatalogPhotoImportPreviewItemOutput
{
    public function __construct(
        #[Groups(['merchant_catalog_photo_import:read'])]
        public int $line,
        #[Groups(['merchant_catalog_photo_import:read'])]
        public string $status,
        #[Groups(['merchant_catalog_photo_import:read'])]
        #[SerializedName('product_reference_id')]
        public ?string $productReferenceId,
        #[Groups(['merchant_catalog_photo_import:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['merchant_catalog_photo_import:read'])]
        public ?string $brand,
        #[Groups(['merchant_catalog_photo_import:read'])]
        public ?string $volume,
        #[Groups(['merchant_catalog_photo_import:read'])]
        public ?string $unit,
        #[Groups(['merchant_catalog_photo_import:read'])]
        public ?string $barcode,
        #[Groups(['merchant_catalog_photo_import:read'])]
        #[SerializedName('suggested_price_tnd')]
        public ?string $suggestedPriceTnd,
        #[Groups(['merchant_catalog_photo_import:read'])]
        public ?string $confidence,
        #[Groups(['merchant_catalog_photo_import:read'])]
        #[SerializedName('already_in_catalog')]
        public bool $alreadyInCatalog,
    ) {
    }
}
