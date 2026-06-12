<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminPromotionItemOutput
{
    public function __construct(
        #[Groups(['admin_promotion_list:read'])]
        public string $id,
        #[Groups(['admin_promotion_list:read'])]
        #[SerializedName('product_name')]
        public string $productName,
        #[Groups(['admin_promotion_list:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['admin_promotion_list:read'])]
        #[SerializedName('store_name')]
        public string $storeName,
        #[Groups(['admin_promotion_list:read'])]
        #[SerializedName('merchant_id')]
        public ?string $merchantId,
        #[Groups(['admin_promotion_list:read'])]
        #[SerializedName('merchant_email')]
        public ?string $merchantEmail,
        #[Groups(['admin_promotion_list:read'])]
        #[SerializedName('price_tnd')]
        public string $priceTnd,
        #[Groups(['admin_promotion_list:read'])]
        #[SerializedName('promotion_price_tnd')]
        public string $promotionPriceTnd,
        #[Groups(['admin_promotion_list:read'])]
        #[SerializedName('promotion_ends_on')]
        public string $promotionEndsOn,
        #[Groups(['admin_promotion_list:read'])]
        #[SerializedName('promotion_active')]
        public bool $promotionActive,
        #[Groups(['admin_promotion_list:read'])]
        #[SerializedName('is_available')]
        public bool $isAvailable,
        #[Groups(['admin_promotion_list:read'])]
        #[SerializedName('is_visible')]
        public bool $isVisible,
    ) {
    }
}
