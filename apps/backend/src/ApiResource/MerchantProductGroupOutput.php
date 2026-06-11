<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Entity\ProductGroup;
use App\Entity\Shop;
use App\Provider\MerchantProductGroupProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/product-groups/{groupId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'groupId' => new Link(fromClass: ProductGroup::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_product_group:read']],
            provider: MerchantProductGroupProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantProductGroupOutput
{
    /**
     * @param list<MerchantProductGroupItemOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_product_group:read', 'merchant_product_group_list:read'])]
        public string $id,
        #[Groups(['merchant_product_group:read', 'merchant_product_group_list:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['merchant_product_group:read', 'merchant_product_group_list:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['merchant_product_group:read', 'merchant_product_group_list:read'])]
        public string $slug,
        #[Groups(['merchant_product_group:read', 'merchant_product_group_list:read'])]
        #[SerializedName('description_fr')]
        public ?string $descriptionFr,
        #[Groups(['merchant_product_group:read', 'merchant_product_group_list:read'])]
        #[SerializedName('description_ar')]
        public ?string $descriptionAr,
        #[Groups(['merchant_product_group:read', 'merchant_product_group_list:read'])]
        #[SerializedName('market_country')]
        public string $marketCountry,
        #[Groups(['merchant_product_group:read', 'merchant_product_group_list:read'])]
        public ?string $icon,
        #[Groups(['merchant_product_group:read', 'merchant_product_group_list:read'])]
        #[SerializedName('sort_order')]
        public int $sortOrder,
        #[Groups(['merchant_product_group:read', 'merchant_product_group_list:read'])]
        #[SerializedName('items_count')]
        public int $itemsCount,
        #[Groups(['merchant_product_group:read'])]
        public array $items = [],
    ) {
    }
}
