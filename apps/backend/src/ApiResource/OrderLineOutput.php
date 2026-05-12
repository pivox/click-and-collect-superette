<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class OrderLineOutput
{
    public function __construct(
        #[Groups(['order:read'])]
        #[SerializedName('merchant_product_id')]
        public string $merchantProductId,
        #[Groups(['order:read'])]
        public int $quantity,
        #[Groups(['order:read'])]
        #[SerializedName('unit_price_tnd')]
        public string $unitPriceTnd,
        #[Groups(['order:read'])]
        #[SerializedName('line_total_tnd')]
        public string $lineTotalTnd,
    ) {
    }
}
