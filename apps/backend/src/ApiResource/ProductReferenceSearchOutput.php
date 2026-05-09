<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Shop;
use App\Provider\ProductReferenceSearchProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/product-references',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            provider: ProductReferenceSearchProvider::class,
            normalizationContext: ['groups' => ['product_reference_search:read']],
            security: "is_granted('ROLE_MERCHANT')",
            parameters: [
                'q' => new QueryParameter(schema: ['type' => 'string'], description: 'Recherche sur nom, marque, code-barres.'),
                'brandId' => new QueryParameter(schema: ['type' => 'string'], description: 'Filtrer par marque (UUID).'),
                'categorySlug' => new QueryParameter(schema: ['type' => 'string'], description: 'Filtrer par slug de catégorie.'),
                'page' => new QueryParameter(schema: ['type' => 'integer', 'default' => 1], description: 'Numéro de page (défaut : 1).'),
                'limit' => new QueryParameter(schema: ['type' => 'integer', 'default' => 20, 'maximum' => 50], description: 'Résultats par page (défaut : 20, max : 50).'),
            ],
        ),
    ],
)]
final readonly class ProductReferenceSearchOutput
{
    /**
     * @param list<ProductReferenceItemOutput> $items
     */
    public function __construct(
        #[Groups(['product_reference_search:read'])]
        public array $items,
        #[Groups(['product_reference_search:read'])]
        public int $total,
        #[Groups(['product_reference_search:read'])]
        public int $page,
        #[Groups(['product_reference_search:read'])]
        public int $limit,
        #[ApiProperty(identifier: true)]
        public ?string $storeId = null,
    ) {
    }
}
