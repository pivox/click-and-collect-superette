<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\BulkLocalProductCreateInput;
use App\Entity\Shop;
use App\Processor\CreateBulkLocalProductProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/local-products/bulk',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: BulkLocalProductCreateInput::class,
            status: 201,
            read: false,
            processor: CreateBulkLocalProductProcessor::class,
            normalizationContext: ['groups' => ['bulk_local_product:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class BulkLocalProductCreatedOutput
{
    /**
     * @param list<array{merchant_product_id: string, local_product_id: string, name_fr: string, price_tnd: string}> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['bulk_local_product:read'])]
        public string $id,
        #[Groups(['bulk_local_product:read'])]
        #[SerializedName('created_count')]
        public int $createdCount,
        #[Groups(['bulk_local_product:read'])]
        public array $items,
    ) {
    }
}
