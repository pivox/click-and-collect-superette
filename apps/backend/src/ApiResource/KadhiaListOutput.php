<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Shop;
use App\Provider\KadhiaCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/stores/{storeId}/kadhias',
            uriVariables: ['storeId' => new Link(fromClass: Shop::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['kadhia_list:read']],
            provider: KadhiaCollectionProvider::class,
            security: "is_granted('ROLE_CUSTOMER')",
            parameters: [
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    description: 'Numéro de page (20 items par page).',
                ),
            ],
        ),
        new Get(
            uriTemplate: '/me/kadhias',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['kadhia_list:read']],
            provider: KadhiaCollectionProvider::class,
            security: "is_granted('ROLE_CUSTOMER')",
            parameters: [
                'status' => new QueryParameter(
                    schema: ['type' => 'string', 'enum' => ['draft', 'submitted']],
                    description: 'Filtrer par statut.',
                ),
                'store_id' => new QueryParameter(
                    schema: ['type' => 'string', 'format' => 'uuid'],
                    description: 'Filtrer par supérette.',
                ),
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    description: 'Numéro de page (20 items par page).',
                ),
            ],
        ),
    ],
)]
final readonly class KadhiaListOutput
{
    /**
     * @param list<KadhiaListItemOutput> $items
     */
    public function __construct(
        #[Groups(['kadhia_list:read'])]
        public array $items,
        #[Groups(['kadhia_list:read'])]
        public int $total,
        #[Groups(['kadhia_list:read'])]
        public int $page,
        #[Groups(['kadhia_list:read'])]
        #[SerializedName('per_page')]
        public int $perPage,
        #[Groups(['kadhia_list:read'])]
        public int $pages,
        #[ApiProperty(identifier: true)]
        public string $id = 'list',
    ) {
    }
}
