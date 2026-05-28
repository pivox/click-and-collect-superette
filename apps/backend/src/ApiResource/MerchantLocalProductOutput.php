<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantLocalProductCreateInput;
use App\Entity\Shop;
use App\Processor\CreateMerchantLocalProductProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/local-products',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantLocalProductCreateInput::class,
            status: 201,
            read: false,
            processor: CreateMerchantLocalProductProcessor::class,
            normalizationContext: ['groups' => ['merchant_local_product:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantLocalProductOutput
{
    public function __construct(
        #[Groups(['merchant_local_product:read'])]
        #[ApiProperty(identifier: false)]
        #[SerializedName('merchant_product_id')]
        public string $merchantProductId,
        #[Groups(['merchant_local_product:read'])]
        #[ApiProperty(identifier: true)]
        #[SerializedName('local_product_id')]
        public string $localProductId,
        #[Groups(['merchant_local_product:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['merchant_local_product:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['merchant_local_product:read'])]
        public ?string $brand,
        #[Groups(['merchant_local_product:read'])]
        public string $category,
        #[Groups(['merchant_local_product:read'])]
        public ?string $volume,
        #[Groups(['merchant_local_product:read'])]
        public string $unit,
        #[Groups(['merchant_local_product:read'])]
        #[SerializedName('price_tnd')]
        public string $priceTnd,
        #[Groups(['merchant_local_product:read'])]
        #[SerializedName('is_available')]
        public bool $isAvailable,
        #[Groups(['merchant_local_product:read'])]
        #[SerializedName('is_visible')]
        public bool $isVisible,
        #[Groups(['merchant_local_product:read'])]
        #[SerializedName('merchant_note')]
        public ?string $merchantNote,
        #[Groups(['merchant_local_product:read'])]
        #[SerializedName('pack_quantity')]
        public int $packQuantity = 1,
    ) {
    }
}
