<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Entity\Shop;
use App\Provider\MerchantProductGroupProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/product-groups',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_product_group_list:read']],
            provider: MerchantProductGroupProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantProductGroupListOutput
{
    /**
     * @param list<MerchantProductGroupSummaryOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_product_group_list:read'])]
        public string $id,
        #[Groups(['merchant_product_group_list:read'])]
        public array $items,
    ) {
    }
}
