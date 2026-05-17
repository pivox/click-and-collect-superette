<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantOrderHistoryItemOutput
{
    public function __construct(
        #[Groups(['merchant_order_history:read'])]
        public string $id,
        #[Groups(['merchant_order_history:read'])]
        public string $status,
        #[Groups(['merchant_order_history:read'])]
        #[SerializedName('status_label_fr')]
        public string $statusLabelFr,
        #[Groups(['merchant_order_history:read'])]
        #[SerializedName('status_label_ar')]
        public string $statusLabelAr,
        #[Groups(['merchant_order_history:read'])]
        public MerchantOrderHistoryCustomerOutput $customer,
        #[Groups(['merchant_order_history:read'])]
        public string $total,
        #[Groups(['merchant_order_history:read'])]
        #[SerializedName('pickup_slot')]
        public ?MerchantOrderHistoryPickupSlotOutput $pickupSlot,
        #[Groups(['merchant_order_history:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['merchant_order_history:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
    ) {
    }
}
