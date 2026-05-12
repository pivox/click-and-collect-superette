<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\StoreSearchProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/stores/search',
            formats: ['json' => ['application/json']],
            provider: StoreSearchProvider::class,
            normalizationContext: ['groups' => ['store_search:read']],
            security: "is_granted('PUBLIC_ACCESS')",
            parameters: [
                'query' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Recherche sur le nom ou la ville de la supérette.',
                ),
                'city' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Filtrer par ville (correspondance exacte, insensible à la casse).',
                ),
            ],
        ),
    ],
)]
final readonly class StoreSearchOutput
{
    /**
     * @param list<StoreSearchItemOutput> $items
     */
    public function __construct(
        #[Groups(['store_search:read'])]
        public array $items,
        #[Groups(['store_search:read'])]
        public int $total,
        #[ApiProperty(identifier: true)]
        public string $id = 'search',
    ) {
    }
}
