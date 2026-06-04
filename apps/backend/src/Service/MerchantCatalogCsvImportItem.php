<?php

declare(strict_types=1);

namespace App\Service;

final readonly class MerchantCatalogCsvImportItem
{
    public function __construct(
        public int $line,
        public string $status,
        public string $merchantProductId,
        public ?string $productReferenceId,
        public ?string $localProductId,
        public string $nameFr,
    ) {
    }
}
