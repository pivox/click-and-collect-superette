<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Entity\Order;
use App\Entity\Shop;
use App\Provider\CustomerOrderStatusHistoryProvider;
use App\Provider\MerchantOrderStatusHistoryProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/orders/{orderId}/status-history',
            uriVariables: ['orderId' => new Link(fromClass: Order::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['order_status_history:read']],
            provider: CustomerOrderStatusHistoryProvider::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/orders/{orderId}/status-history',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'orderId' => new Link(fromClass: Order::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['order_status_history:read']],
            provider: MerchantOrderStatusHistoryProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class OrderStatusHistoryOutput
{
    /**
     * @param list<OrderStatusTransitionOutput> $transitions
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['order_status_history:read'])]
        #[SerializedName('order_id')]
        public string $orderId,
        #[Groups(['order_status_history:read'])]
        public array $transitions,
    ) {
    }
}
