<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class KadhiaReplacementLineOutput
{
    /**
     * @param list<StoreCatalogProductOutput> $alternatives
     */
    public function __construct(
        #[Groups(['kadhia_replacements:read'])]
        #[SerializedName('line_id')]
        public string $lineId,
        #[Groups(['kadhia_replacements:read'])]
        #[SerializedName('merchant_product_id')]
        public string $merchantProductId,
        #[Groups(['kadhia_replacements:read'])]
        #[SerializedName('product_name')]
        public string $productName,
        #[Groups(['kadhia_replacements:read'])]
        public int $quantity,
        #[Groups(['kadhia_replacements:read'])]
        public array $alternatives,
    ) {
    }
}
