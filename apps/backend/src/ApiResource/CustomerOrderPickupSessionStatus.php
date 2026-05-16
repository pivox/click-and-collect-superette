<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class CustomerOrderPickupSessionStatus
{
    public function __construct(
        #[Groups(['order_status:read'])]
        public bool $exists,
        #[Groups(['order_status:read'])]
        #[SerializedName('is_scanned')]
        public bool $isScanned,
        #[Groups(['order_status:read'])]
        #[SerializedName('merchant_confirmed')]
        public bool $merchantConfirmed,
        #[Groups(['order_status:read'])]
        #[SerializedName('customer_confirmed')]
        public bool $customerConfirmed,
        #[Groups(['order_status:read'])]
        #[SerializedName('is_used')]
        public bool $isUsed,
        #[Groups(['order_status:read'])]
        #[SerializedName('force_completed_by_merchant')]
        public bool $forceCompletedByMerchant,
    ) {
    }

    public static function none(): self
    {
        return new self(
            exists: false,
            isScanned: false,
            merchantConfirmed: false,
            customerConfirmed: false,
            isUsed: false,
            forceCompletedByMerchant: false,
        );
    }
}
