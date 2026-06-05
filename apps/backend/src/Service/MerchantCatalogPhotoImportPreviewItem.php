<?php

declare(strict_types=1);

namespace App\Service;

final readonly class MerchantCatalogPhotoImportPreviewItem
{
    public function __construct(
        public int $line,
        public string $status,
        public ?string $productReferenceId,
        public string $nameFr,
        public ?string $brand,
        public ?string $volume,
        public ?string $unit,
        public ?string $barcode,
        public ?string $suggestedPriceTnd,
        public ?string $confidence,
        public bool $alreadyInCatalog,
    ) {
    }
}
