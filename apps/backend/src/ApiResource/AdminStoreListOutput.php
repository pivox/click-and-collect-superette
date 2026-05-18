<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\AdminStoreCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/stores',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_store_list:read']],
            provider: AdminStoreCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 1],
                    description: 'Numéro de page (défaut : 1).',
                ),
                'limit' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 20],
                    description: 'Résultats par page (défaut : 20, max : 50).',
                ),
                'is_active' => new QueryParameter(
                    schema: ['type' => 'boolean'],
                    description: 'Filtre optionnel sur les supérettes actives ou inactives.',
                ),
            ],
        ),
    ],
)]
final readonly class AdminStoreListOutput
{
    /**
     * @param list<AdminStoreOutput> $items
     */
    public function __construct(
        // API Platform identifier only; not part of the serialized collection payload.
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_store_list:read'])]
        public array $items,
        #[Groups(['admin_store_list:read'])]
        public int $page,
        #[Groups(['admin_store_list:read'])]
        public int $limit,
        #[Groups(['admin_store_list:read'])]
        public int $total,
    ) {
    }
}
