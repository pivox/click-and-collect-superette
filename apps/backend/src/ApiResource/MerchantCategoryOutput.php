<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantCategoryCreateInput;
use App\Dto\MerchantCategoryUpdateInput;
use App\Entity\MerchantCategory;
use App\Entity\Shop;
use App\Processor\CreateMerchantCategoryProcessor;
use App\Processor\DeleteMerchantCategoryProcessor;
use App\Processor\UpdateMerchantCategoryProcessor;
use App\Provider\MerchantCategoryCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/merchant/stores/{storeId}/categories',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            provider: MerchantCategoryCollectionProvider::class,
            normalizationContext: ['groups' => ['merchant_category:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/categories',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantCategoryCreateInput::class,
            read: false,
            status: 201,
            processor: CreateMerchantCategoryProcessor::class,
            normalizationContext: ['groups' => ['merchant_category:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Patch(
            uriTemplate: '/merchant/categories/{merchantCategoryId}',
            uriVariables: [
                'merchantCategoryId' => new Link(fromClass: MerchantCategory::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantCategoryUpdateInput::class,
            read: false,
            processor: UpdateMerchantCategoryProcessor::class,
            normalizationContext: ['groups' => ['merchant_category:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Delete(
            uriTemplate: '/merchant/categories/{merchantCategoryId}',
            uriVariables: [
                'merchantCategoryId' => new Link(fromClass: MerchantCategory::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            read: false,
            output: false,
            processor: DeleteMerchantCategoryProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantCategoryOutput
{
    public function __construct(
        #[Groups(['merchant_category:read'])]
        #[ApiProperty(identifier: false)]
        public string $id,
        #[Groups(['merchant_category:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['merchant_category:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['merchant_category:read'])]
        public string $slug,
        #[Groups(['merchant_category:read'])]
        #[SerializedName('parent_id')]
        public ?string $parentId,
        #[Groups(['merchant_category:read'])]
        #[SerializedName('sort_order')]
        public int $sortOrder,
        #[Groups(['merchant_category:read'])]
        public bool $active,
        #[Groups(['merchant_category:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['merchant_category:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
    ) {
    }
}
