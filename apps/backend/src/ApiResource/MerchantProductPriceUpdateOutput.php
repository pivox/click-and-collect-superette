<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Dto\MerchantProductPriceUpdateInput;
use App\Entity\MerchantProduct;
use App\Processor\UpdateMerchantProductPriceProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/merchant/products/{merchantProductId}/price',
            uriVariables: [
                'merchantProductId' => new Link(fromClass: MerchantProduct::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantProductPriceUpdateInput::class,
            read: false,
            status: 200,
            processor: UpdateMerchantProductPriceProcessor::class,
            normalizationContext: ['groups' => ['merchant_product_price:update']],
            security: "is_granted('ROLE_MERCHANT')",
            validate: true,
        ),
    ],
)]
final readonly class MerchantProductPriceUpdateOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_product_price:update'])]
        public string $id,
        #[Groups(['merchant_product_price:update'])]
        public string $currentPrice,
        #[Groups(['merchant_product_price:update'])]
        public string $currency,
        #[Groups(['merchant_product_price:update'])]
        public ?MerchantProductPriceHistoryItemOutput $lastPriceChange,
    ) {
    }
}
