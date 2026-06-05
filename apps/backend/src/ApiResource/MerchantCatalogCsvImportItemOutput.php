<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantCatalogCsvImportItemOutput
{
    public function __construct(
        #[Groups(['merchant_catalog_import:read'])]
        public int $line,
        #[Groups(['merchant_catalog_import:read'])]
        public string $status,
        #[Groups(['merchant_catalog_import:read'])]
        #[SerializedName('merchant_product_id')]
        public string $merchantProductId,
        #[Groups(['merchant_catalog_import:read'])]
        #[SerializedName('product_reference_id')]
        public ?string $productReferenceId,
        #[Groups(['merchant_catalog_import:read'])]
        #[SerializedName('local_product_id')]
        public ?string $localProductId,
        #[Groups(['merchant_catalog_import:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
    ) {
    }
}
