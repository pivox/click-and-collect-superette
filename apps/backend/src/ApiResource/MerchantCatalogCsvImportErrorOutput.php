<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class MerchantCatalogCsvImportErrorOutput
{
    public function __construct(
        #[Groups(['merchant_catalog_import:read'])]
        public int $line,
        #[Groups(['merchant_catalog_import:read'])]
        public string $code,
        #[Groups(['merchant_catalog_import:read'])]
        public ?string $field,
        #[Groups(['merchant_catalog_import:read'])]
        public string $message,
    ) {
    }
}
