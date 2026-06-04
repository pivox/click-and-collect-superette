<?php

declare(strict_types=1);

namespace App\Service;

final readonly class MerchantCatalogCsvParseResult
{
    /**
     * @param list<MerchantCatalogCsvParsedRow>  $rows
     * @param list<MerchantCatalogCsvParseError> $errors
     */
    public function __construct(
        public array $rows,
        public array $errors,
        public int $ignored,
    ) {
    }
}
