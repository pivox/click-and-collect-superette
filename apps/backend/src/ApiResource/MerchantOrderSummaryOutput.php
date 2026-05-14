<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantOrderSummaryOutput
{
    /**
     * @param array{id: string, starts_at: string, ends_at: string}|null $pickupSlot
     */
    public function __construct(
        #[Groups(['merchant_order_summary:read'])]
        public string $id,
        #[Groups(['merchant_order_summary:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['merchant_order_summary:read'])]
        public string $status,
        #[Groups(['merchant_order_summary:read'])]
        #[SerializedName('total_tnd')]
        public string $totalTnd,
        #[Groups(['merchant_order_summary:read'])]
        #[SerializedName('pickup_slot')]
        public ?array $pickupSlot,
        #[Groups(['merchant_order_summary:read'])]
        #[SerializedName('line_count')]
        public int $lineCount,
        #[Groups(['merchant_order_summary:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['merchant_order_summary:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
    ) {
    }
}
