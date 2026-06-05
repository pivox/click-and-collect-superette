<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\ProductUnit;

final readonly class MerchantCatalogCsvParsedRow
{
    public function __construct(
        public int $line,
        public string $nameFr,
        public ?string $nameAr,
        public string $brand,
        public string $volume,
        public ProductUnit $unit,
        public string $priceTnd,
        public bool $isAvailable,
        public bool $isVisible,
        public ?string $barcode,
        public ?string $category,
        public ?string $variantFr,
        public ?string $merchantNote,
        public int $packQuantity,
    ) {
    }
}
