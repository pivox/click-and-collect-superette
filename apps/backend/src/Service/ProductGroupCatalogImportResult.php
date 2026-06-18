<?php

declare(strict_types=1);

namespace App\Service;

final readonly class ProductGroupCatalogImportResult
{
    /**
     * @param list<ProductGroupCatalogImportError> $errors
     */
    public function __construct(
        public int $created,
        public int $alreadyInCatalog,
        public int $skipped,
        public int $requiresPriceCompletion,
        public array $errors,
    ) {
    }
}
