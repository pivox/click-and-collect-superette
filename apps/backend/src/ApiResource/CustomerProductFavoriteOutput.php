<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Dto\CustomerProductFavoriteInput;
use App\Entity\MerchantProduct;
use App\Processor\UpdateProductFavoriteProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/me/products/{merchantProductId}/favorite',
            uriVariables: [
                'merchantProductId' => new Link(fromClass: MerchantProduct::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: CustomerProductFavoriteInput::class,
            normalizationContext: ['groups' => ['customer_product_favorite:read']],
            status: 200,
            read: false,
            processor: UpdateProductFavoriteProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class CustomerProductFavoriteOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['customer_product_favorite:read'])]
        #[SerializedName('merchant_product_id')]
        public string $merchantProductId,
        #[Groups(['customer_product_favorite:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['customer_product_favorite:read'])]
        #[SerializedName('is_favorite')]
        public bool $isFavorite,
    ) {
    }
}
