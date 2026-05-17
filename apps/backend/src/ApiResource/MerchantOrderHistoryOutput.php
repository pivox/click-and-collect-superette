<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Shop;
use App\Provider\MerchantOrderHistoryProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/orders/history',
            uriVariables: ['storeId' => new Link(fromClass: Shop::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_order_history:read']],
            provider: MerchantOrderHistoryProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
            parameters: [
                'status' => new QueryParameter(schema: ['type' => 'string']),
                'date_from' => new QueryParameter(schema: ['type' => 'string']),
                'date_to' => new QueryParameter(schema: ['type' => 'string']),
                'query' => new QueryParameter(schema: ['type' => 'string']),
                'page' => new QueryParameter(schema: ['type' => 'integer', 'default' => 1]),
                'limit' => new QueryParameter(schema: ['type' => 'integer', 'default' => 20]),
            ],
        ),
    ],
)]
final readonly class MerchantOrderHistoryOutput
{
    /**
     * @param list<MerchantOrderHistoryItemOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['merchant_order_history:read'])]
        public array $items,
        #[Groups(['merchant_order_history:read'])]
        public int $page,
        #[Groups(['merchant_order_history:read'])]
        public int $limit,
        #[Groups(['merchant_order_history:read'])]
        public int $total,
    ) {
    }
}
