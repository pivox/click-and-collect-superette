<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Entity\Shop;
use App\Provider\StoreSuggestionsProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/stores/{storeId}/suggestions',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['store_suggestions:read', 'store_catalog:read']],
            provider: StoreSuggestionsProvider::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class StoreSuggestionsOutput
{
    /**
     * @param list<StoreCatalogProductOutput> $frequentlyBoughtTogether
     * @param list<StoreCatalogProductOutput> $recentlyOrdered
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['store_suggestions:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['store_suggestions:read'])]
        #[SerializedName('frequently_bought_together')]
        public array $frequentlyBoughtTogether,
        #[Groups(['store_suggestions:read'])]
        #[SerializedName('recently_ordered')]
        public array $recentlyOrdered,
    ) {
    }
}
