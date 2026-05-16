<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Entity\Shop;
use App\Provider\MerchantExceptionalClosureCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/merchant/stores/{storeId}/exceptional-closures',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            provider: MerchantExceptionalClosureCollectionProvider::class,
            normalizationContext: ['groups' => ['merchant_exceptional_closure_collection:read', 'merchant_exceptional_closure:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantExceptionalClosureCollectionOutput
{
    /**
     * @param list<MerchantExceptionalClosureOutput> $items
     */
    public function __construct(
        #[Groups(['merchant_exceptional_closure_collection:read'])]
        public int $total,
        #[Groups(['merchant_exceptional_closure_collection:read'])]
        public array $items,
    ) {
    }
}
