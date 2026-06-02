<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Shop;
use App\Provider\MerchantCatalogProductCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/catalog',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_catalog:read']],
            provider: MerchantCatalogProductCollectionProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
            parameters: [
                'q' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Recherche textuelle (nom, marque, catégorie, note marchand).',
                ),
                'availability' => new QueryParameter(
                    schema: ['type' => 'string', 'enum' => ['available', 'unavailable']],
                    description: 'Filtrer par disponibilité : available ou unavailable.',
                ),
                'visibility' => new QueryParameter(
                    schema: ['type' => 'string', 'enum' => ['visible', 'hidden']],
                    description: 'Filtrer par visibilité : visible ou hidden.',
                ),
                'category' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Filtrer par nom de catégorie (catégorie marchand ou catégorie référentiel).',
                ),
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 1],
                    description: 'Numéro de page (défaut : 1).',
                ),
                'limit' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 50],
                    description: 'Résultats par page (défaut : 50, max : 100).',
                ),
            ],
        ),
    ],
)]
final readonly class MerchantCatalogListOutput
{
    /**
     * @param list<MerchantCatalogProductOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['merchant_catalog:read'])]
        public array $items,
        #[Groups(['merchant_catalog:read'])]
        public int $total,
        #[Groups(['merchant_catalog:read'])]
        public int $page,
        #[Groups(['merchant_catalog:read'])]
        public int $limit,
        #[Groups(['merchant_catalog:read'])]
        public int $pages,
    ) {
    }
}
