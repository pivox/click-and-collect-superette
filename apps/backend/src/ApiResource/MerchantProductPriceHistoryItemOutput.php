<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class MerchantProductPriceHistoryItemOutput
{
    public function __construct(
        #[Groups(['merchant_product_price_history:read', 'merchant_product_price:update'])]
        public ?string $oldPrice,
        #[Groups(['merchant_product_price_history:read', 'merchant_product_price:update'])]
        public string $newPrice,
        #[Groups(['merchant_product_price_history:read', 'merchant_product_price:update'])]
        public string $currency,
        #[Groups(['merchant_product_price_history:read', 'merchant_product_price:update'])]
        public string $changeType,
        #[Groups(['merchant_product_price_history:read', 'merchant_product_price:update'])]
        public string $source,
        #[Groups(['merchant_product_price_history:read', 'merchant_product_price:update'])]
        public ?string $reason,
        #[Groups(['merchant_product_price_history:read', 'merchant_product_price:update'])]
        public ?string $changedByUserId,
        #[Groups(['merchant_product_price_history:read', 'merchant_product_price:update'])]
        public string $changedAt,
    ) {
    }
}
