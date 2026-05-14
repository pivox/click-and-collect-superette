<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantOrderLineOutput
{
    public function __construct(
        #[Groups(['merchant_order_detail:read'])]
        #[SerializedName('merchant_product_id')]
        public string $merchantProductId,
        #[Groups(['merchant_order_detail:read'])]
        #[SerializedName('product_name')]
        public ?string $productName,
        #[Groups(['merchant_order_detail:read'])]
        public int $quantity,
        #[Groups(['merchant_order_detail:read'])]
        #[SerializedName('unit_price_tnd')]
        public string $unitPriceTnd,
        #[Groups(['merchant_order_detail:read'])]
        #[SerializedName('line_total_tnd')]
        public string $lineTotalTnd,
        #[Groups(['merchant_order_detail:read'])]
        public bool $prepared,
    ) {
    }
}
