<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\CustomerStoreListProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/stores',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['customer_store_list:read', 'customer_store:read']],
            provider: CustomerStoreListProvider::class,
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
final readonly class CustomerStoreListOutput
{
    /**
     * @param list<CustomerStoreOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['customer_store_list:read'])]
        public array $items,
        #[Groups(['customer_store_list:read'])]
        public int $total,
        #[Groups(['customer_store_list:read'])]
        public int $page,
        #[Groups(['customer_store_list:read'])]
        public int $limit,
    ) {
    }
}
