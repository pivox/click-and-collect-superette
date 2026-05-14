<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Shop;
use App\Provider\MerchantOrderCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/orders',
            uriVariables: ['storeId' => new Link(fromClass: Shop::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_order_summary:read']],
            provider: MerchantOrderCollectionProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
            parameters: [
                'status' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Filtrer par statut (submitted, accepted, preparing, ready, …).',
                ),
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
final readonly class MerchantOrderListOutput
{
    /**
     * @param list<MerchantOrderSummaryOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['merchant_order_summary:read'])]
        public array $items,
        #[Groups(['merchant_order_summary:read'])]
        public int $total,
        #[Groups(['merchant_order_summary:read'])]
        public int $page,
        #[Groups(['merchant_order_summary:read'])]
        public int $limit,
    ) {
    }
}
