<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class KadhiaLineOutput
{
    public function __construct(
        #[Groups(['kadhia:read'])]
        public string $id,
        #[Groups(['kadhia:read'])]
        #[SerializedName('merchant_product_id')]
        public string $merchantProductId,
        #[Groups(['kadhia:read'])]
        #[SerializedName('product_name')]
        public string $productName,
        #[Groups(['kadhia:read'])]
        #[SerializedName('unit_price_tnd')]
        public string $unitPriceTnd,
        #[Groups(['kadhia:read'])]
        public int $quantity,
        #[Groups(['kadhia:read'])]
        #[SerializedName('subtotal_tnd')]
        public string $subtotalTnd,
        // False when the product can no longer be ordered (unavailable,
        // hidden or reference no longer approved) — drives the rupture badge.
        #[Groups(['kadhia:read'])]
        #[SerializedName('is_available')]
        public bool $isAvailable = true,
    ) {
    }
}
