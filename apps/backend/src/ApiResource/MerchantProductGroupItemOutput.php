<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantProductGroupItemOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_product_group:read'])]
        public string $id,
        #[Groups(['merchant_product_group:read'])]
        #[SerializedName('sort_order')]
        public int $sortOrder,
        #[Groups(['merchant_product_group:read'])]
        public string $importance,
        #[Groups(['merchant_product_group:read'])]
        public string $status,
        #[Groups(['merchant_product_group:read'])]
        #[SerializedName('product_reference')]
        public MerchantProductGroupReferenceOutput $productReference,
    ) {
    }
}
