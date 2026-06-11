<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminProductGroupItemOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_product_group:read'])]
        public string $id,
        #[Groups(['admin_product_group:read'])]
        #[SerializedName('sort_order')]
        public int $sortOrder,
        #[Groups(['admin_product_group:read'])]
        public string $importance,
        #[Groups(['admin_product_group:read'])]
        #[SerializedName('product_reference')]
        public AdminProductGroupReferenceOutput $productReference,
        #[Groups(['admin_product_group:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['admin_product_group:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
    ) {
    }
}
