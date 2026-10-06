<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantDashboardPickupOrderOutput
{
    /**
     * @param array{starts_at: string, ends_at: string} $pickupSlot
     */
    public function __construct(
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('order_id')]
        public string $orderId,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('order_number_display')]
        public ?string $orderNumberDisplay,
        #[Groups(['merchant_dashboard:read'])]
        public string $status,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('pickup_slot')]
        public array $pickupSlot,
    ) {
    }
}
