<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\StoreCatalogProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/stores/{storeId}/catalog',
            formats: ['json' => ['application/json']],
            provider: StoreCatalogProvider::class,
            normalizationContext: ['groups' => ['store_catalog:read']],
            security: "is_granted('PUBLIC_ACCESS')",
            parameters: [
                'query' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Recherche simple sur nom, marque, format et champs produit publics.',
                ),
                'category' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Slug de catégorie produit.',
                ),
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1],
                    description: 'Page du catalogue, à partir de 1.',
                ),
                'items_per_page' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    description: 'Nombre de produits par page, limité à 100.',
                ),
            ],
        ),
    ],
)]
final readonly class StoreCatalogOutput
{
    /**
     * @param list<StoreCatalogProductOutput>  $items
     * @param list<StoreCatalogCategoryOutput> $categories
     */
    public function __construct(
        #[Groups(['store_catalog:read'])]
        public array $items,
        #[Groups(['store_catalog:read'])]
        public array $categories = [],
        #[Groups(['store_catalog:read'])]
        public int $page = 1,
        #[Groups(['store_catalog:read'])]
        #[SerializedName('items_per_page')]
        public int $itemsPerPage = 30,
        #[Groups(['store_catalog:read'])]
        public int $total = 0,
        #[Groups(['store_catalog:read'])]
        public int $pages = 1,
        #[ApiProperty(identifier: true)]
        public ?string $storeId = null,
    ) {
    }
}
