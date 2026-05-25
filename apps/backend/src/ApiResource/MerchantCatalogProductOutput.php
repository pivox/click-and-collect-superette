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
use App\Dto\MerchantCatalogCreateInput;
use App\Dto\MerchantCatalogUpdateInput;
use App\Entity\MerchantProduct;
use App\Entity\Shop;
use App\Processor\CreateMerchantCatalogProductProcessor;
use App\Processor\DeleteMerchantCatalogProductProcessor;
use App\Processor\UpdateMerchantCatalogProductProcessor;
use App\Provider\MerchantCatalogProductCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/merchant/stores/{storeId}/catalog',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            itemUriTemplate: '/merchant/catalog/{merchantProductId}',
            formats: ['json' => ['application/json']],
            provider: MerchantCatalogProductCollectionProvider::class,
            normalizationContext: ['groups' => ['merchant_catalog:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/catalog',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            itemUriTemplate: '/merchant/catalog/{merchantProductId}',
            formats: ['json' => ['application/json']],
            input: MerchantCatalogCreateInput::class,
            output: false,
            status: 201,
            read: false,
            processor: CreateMerchantCatalogProductProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Patch(
            uriTemplate: '/merchant/catalog/{merchantProductId}',
            uriVariables: [
                'merchantProductId' => new Link(fromClass: MerchantProduct::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantCatalogUpdateInput::class,
            output: false,
            status: 200,
            read: false,
            processor: UpdateMerchantCatalogProductProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Delete(
            uriTemplate: '/merchant/catalog/{merchantProductId}',
            uriVariables: [
                'merchantProductId' => new Link(fromClass: MerchantProduct::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            read: false,
            output: false,
            processor: DeleteMerchantCatalogProductProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantCatalogProductOutput
{
    public function __construct(
        #[Groups(['merchant_catalog:read'])]
        #[ApiProperty(identifier: false)]
        public string $id,
        #[Groups(['merchant_catalog:read'])]
        #[SerializedName('product_reference_id')]
        public ?string $productReferenceId,
        #[Groups(['merchant_catalog:read'])]
        #[SerializedName('local_product_id')]
        public ?string $localProductId,
        #[Groups(['merchant_catalog:read'])]
        #[SerializedName('merchant_category_id')]
        public ?string $merchantCategoryId,
        #[Groups(['merchant_catalog:read'])]
        #[SerializedName('merchant_category_name')]
        public ?string $merchantCategoryName,
        #[Groups(['merchant_catalog:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['merchant_catalog:read'])]
        public ?string $brand,
        #[Groups(['merchant_catalog:read'])]
        public string $category,
        #[Groups(['merchant_catalog:read'])]
        public ?string $volume,
        #[Groups(['merchant_catalog:read'])]
        public string $unit,
        #[Groups(['merchant_catalog:read'])]
        #[SerializedName('price_tnd')]
        public string $priceTnd,
        #[Groups(['merchant_catalog:read'])]
        #[SerializedName('is_available')]
        public bool $isAvailable,
        #[Groups(['merchant_catalog:read'])]
        #[SerializedName('is_visible')]
        public bool $isVisible,
        #[Groups(['merchant_catalog:read'])]
        #[SerializedName('merchant_note')]
        public ?string $merchantNote,
    ) {
    }
}
