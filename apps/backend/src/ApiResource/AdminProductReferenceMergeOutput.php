<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Dto\AdminMergeProductReferenceInput;
use App\Processor\AdminMergeProductReferenceProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/admin/product-references/{productReferenceId<[0-9a-fA-F\-]{32,36}>}/merge',
            uriVariables: [
                'productReferenceId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: AdminMergeProductReferenceInput::class,
            normalizationContext: ['groups' => ['admin_product_reference_merge:read', 'admin_product_reference_list:read']],
            read: false,
            processor: AdminMergeProductReferenceProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminProductReferenceMergeOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_product_reference_merge:read'])]
        public AdminProductReferenceOutput $kept,
        #[Groups(['admin_product_reference_merge:read'])]
        public AdminProductReferenceOutput $absorbed,
        #[Groups(['admin_product_reference_merge:read'])]
        #[SerializedName('moved_offer_count')]
        public int $movedOfferCount,
        #[Groups(['admin_product_reference_merge:read'])]
        #[SerializedName('removed_conflicting_offer_count')]
        public int $removedConflictingOfferCount,
        #[Groups(['admin_product_reference_merge:read'])]
        #[SerializedName('history_id')]
        public string $historyId,
    ) {
    }
}
