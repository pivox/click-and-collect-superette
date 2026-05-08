<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\ProductReferenceProposalCreateInput;
use App\Entity\Shop;
use App\Processor\CreateProductReferenceProposalProcessor;
use App\Provider\ProductReferenceProposalCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/merchant/stores/{storeId}/product-proposals',
            uriVariables: ['storeId' => new Link(fromClass: Shop::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            provider: ProductReferenceProposalCollectionProvider::class,
            normalizationContext: ['groups' => ['product_proposal:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/product-proposals',
            uriVariables: ['storeId' => new Link(fromClass: Shop::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            input: ProductReferenceProposalCreateInput::class,
            output: false,
            status: 201,
            read: false,
            processor: CreateProductReferenceProposalProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class ProductReferenceProposalOutput
{
    public function __construct(
        #[Groups(['product_proposal:read'])]
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['product_proposal:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['product_proposal:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['product_proposal:read'])]
        public string $status,
        #[Groups(['product_proposal:read'])]
        public string $category,
        #[Groups(['product_proposal:read'])]
        #[SerializedName('category_slug')]
        public string $categorySlug,
        #[Groups(['product_proposal:read'])]
        public ?string $brand,
        #[Groups(['product_proposal:read'])]
        #[SerializedName('brand_name')]
        public ?string $brandName,
        #[Groups(['product_proposal:read'])]
        public ?string $barcode,
        #[Groups(['product_proposal:read'])]
        #[SerializedName('rejection_reason')]
        public ?string $rejectionReason,
        #[Groups(['product_proposal:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
    ) {}
}
