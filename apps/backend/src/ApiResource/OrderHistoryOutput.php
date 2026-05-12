<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\OrderCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/orders',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['order:read']],
            provider: OrderCollectionProvider::class,
            security: "is_granted('ROLE_CUSTOMER')",
            parameters: [
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 1],
                    description: 'Numéro de page (défaut : 1).',
                ),
                'limit' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 20, 'maximum' => 50],
                    description: 'Résultats par page (défaut : 20, max : 50).',
                ),
            ],
        ),
    ],
)]
final readonly class OrderHistoryOutput
{
    /**
     * @param list<OrderOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['order:read'])]
        public array $items,
        #[Groups(['order:read'])]
        public int $total,
        #[Groups(['order:read'])]
        public int $page,
        #[Groups(['order:read'])]
        public int $limit,
    ) {
    }
}
