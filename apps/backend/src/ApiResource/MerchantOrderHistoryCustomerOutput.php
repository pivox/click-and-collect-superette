<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantOrderHistoryCustomerOutput
{
    public function __construct(
        #[Groups(['merchant_order_history:read'])]
        #[SerializedName('first_name')]
        public ?string $firstName,
        #[Groups(['merchant_order_history:read'])]
        #[SerializedName('last_name')]
        public ?string $lastName,
        #[Groups(['merchant_order_history:read'])]
        public ?string $phone,
    ) {
    }
}
