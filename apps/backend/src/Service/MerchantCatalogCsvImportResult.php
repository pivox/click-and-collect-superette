<?php

declare(strict_types=1);

namespace App\Service;

final readonly class MerchantCatalogCsvImportResult
{
    /**
     * @param list<MerchantCatalogCsvImportItem> $items
     * @param list<MerchantCatalogCsvParseError> $errors
     */
    public function __construct(
        public string $shopId,
        public int $created,
        public int $updated,
        public int $ignored,
        public array $items,
        public array $errors,
    ) {
    }
}
