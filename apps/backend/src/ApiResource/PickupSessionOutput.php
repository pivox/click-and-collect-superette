<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Entity\Order;
use App\Provider\CustomerPickupSessionProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/orders/{orderId}/pickup-session',
            uriVariables: ['orderId' => new Link(fromClass: Order::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['pickup_session:read']],
            provider: CustomerPickupSessionProvider::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class PickupSessionOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['pickup_session:read'])]
        public string $id,
        #[Groups(['pickup_session:read'])]
        public string $token,
        #[Groups(['pickup_session:read'])]
        #[SerializedName('expires_at')]
        public string $expiresAt,
        #[Groups(['pickup_session:read'])]
        #[SerializedName('is_used')]
        public bool $isUsed,
        #[Groups(['pickup_session:read'])]
        #[SerializedName('is_expired')]
        public bool $isExpired,
        #[Groups(['pickup_session:read'])]
        #[SerializedName('qr_payload')]
        public string $qrPayload,
    ) {
    }
}
