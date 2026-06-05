<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\ProductUnit;

final readonly class MerchantCatalogPhotoDetectedProduct
{
    public function __construct(
        public int $line,
        public string $nameFr,
        public ?string $brand,
        public ?string $volume,
        public ?ProductUnit $unit,
        public ?string $barcode,
        public ?string $suggestedPriceTnd,
        public ?string $confidence,
    ) {
    }
}
