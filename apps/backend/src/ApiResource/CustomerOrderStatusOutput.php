<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Entity\Order;
use App\Provider\CustomerOrderStatusProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/orders/{orderId}/status',
            uriVariables: ['orderId' => new Link(fromClass: Order::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['order_status:read']],
            provider: CustomerOrderStatusProvider::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class CustomerOrderStatusOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['order_status:read'])]
        #[SerializedName('order_id')]
        public string $orderId,
        #[Groups(['order_status:read'])]
        public string $status,
        #[Groups(['order_status:read'])]
        #[SerializedName('status_label_fr')]
        public string $statusLabelFr,
        #[Groups(['order_status:read'])]
        #[SerializedName('status_label_ar')]
        public string $statusLabelAr,
        #[Groups(['order_status:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
        #[Groups(['order_status:read'])]
        #[SerializedName('pickup_session')]
        public CustomerOrderPickupSessionStatus $pickupSession,
    ) {
    }
}
