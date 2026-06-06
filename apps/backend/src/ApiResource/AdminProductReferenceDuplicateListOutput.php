<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\AdminProductReferenceDuplicateCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/product-references/duplicates',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_product_reference_duplicates:read', 'admin_product_reference_list:read']],
            provider: AdminProductReferenceDuplicateCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'page' => new QueryParameter(schema: ['type' => 'integer', 'default' => 1]),
                'limit' => new QueryParameter(schema: ['type' => 'integer', 'default' => 20]),
            ],
        ),
    ],
)]
final readonly class AdminProductReferenceDuplicateListOutput
{
    /**
     * @param list<AdminProductReferenceDuplicateCandidateOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_product_reference_duplicates:read'])]
        public array $items,
        #[Groups(['admin_product_reference_duplicates:read'])]
        public int $page,
        #[Groups(['admin_product_reference_duplicates:read'])]
        public int $limit,
        #[Groups(['admin_product_reference_duplicates:read'])]
        public int $total,
    ) {
    }
}
