<?php

declare(strict_types=1);

namespace App\Service;

final readonly class MerchantCatalogCsvParseError
{
    public function __construct(
        public int $line,
        public string $code,
        public ?string $field,
        public string $message,
    ) {
    }
}
