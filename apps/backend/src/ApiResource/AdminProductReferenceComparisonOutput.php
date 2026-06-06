<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\AdminProductReferenceComparisonProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/product-references/compare',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_product_reference_compare:read', 'admin_product_reference_list:read']],
            provider: AdminProductReferenceComparisonProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'left' => new QueryParameter(schema: ['type' => 'string']),
                'right' => new QueryParameter(schema: ['type' => 'string']),
            ],
        ),
    ],
)]
final readonly class AdminProductReferenceComparisonOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_product_reference_compare:read'])]
        public AdminProductReferenceOutput $left,
        #[Groups(['admin_product_reference_compare:read'])]
        public AdminProductReferenceOutput $right,
        #[Groups(['admin_product_reference_compare:read'])]
        #[SerializedName('left_offer_count')]
        public int $leftOfferCount,
        #[Groups(['admin_product_reference_compare:read'])]
        #[SerializedName('right_offer_count')]
        public int $rightOfferCount,
        #[Groups(['admin_product_reference_compare:read'])]
        public string $reason,
    ) {
    }
}
