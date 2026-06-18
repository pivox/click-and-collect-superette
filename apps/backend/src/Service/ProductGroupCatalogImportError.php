<?php

declare(strict_types=1);

namespace App\Service;

final readonly class ProductGroupCatalogImportError
{
    public function __construct(
        public string $productReferenceId,
        public string $code,
        public string $message,
        public ?string $productGroupId = null,
    ) {
    }
}
