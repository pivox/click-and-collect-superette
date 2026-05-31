<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Entity\MerchantProduct;
use App\Provider\AdminMerchantProductPriceHistoryProvider;
use App\Provider\MerchantProductPriceHistoryProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/products/{merchantProductId}/price-history',
            uriVariables: [
                'merchantProductId' => new Link(fromClass: MerchantProduct::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_product_price_history:read']],
            provider: MerchantProductPriceHistoryProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Get(
            uriTemplate: '/admin/merchant-products/{merchantProductId}/price-history',
            uriVariables: [
                'merchantProductId' => new Link(fromClass: MerchantProduct::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_product_price_history:read']],
            provider: AdminMerchantProductPriceHistoryProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class MerchantProductPriceHistoryOutput
{
    /**
     * @param list<MerchantProductPriceHistoryItemOutput> $priceHistory
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_product_price_history:read'])]
        public string $merchantProductId,
        #[Groups(['merchant_product_price_history:read'])]
        public string $currentPrice,
        #[Groups(['merchant_product_price_history:read'])]
        public string $currency,
        #[Groups(['merchant_product_price_history:read'])]
        public array $priceHistory,
    ) {
    }
}
