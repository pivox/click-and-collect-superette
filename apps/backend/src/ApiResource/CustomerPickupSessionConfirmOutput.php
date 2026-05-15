<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Processor\CustomerPickupSessionConfirmProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/me/pickup-sessions/{id}/confirm',
            uriVariables: [
                'id' => new Link(fromClass: CustomerPickupSessionConfirmOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['customer_pickup_session_confirm:read'], 'skip_null_values' => false],
            input: false,
            status: 200,
            read: false,
            processor: CustomerPickupSessionConfirmProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class CustomerPickupSessionConfirmOutput
{
    public function __construct(
        #[ApiProperty(identifier: false)]
        #[Groups(['customer_pickup_session_confirm:read'])]
        public string $id,
        #[Groups(['customer_pickup_session_confirm:read'])]
        #[SerializedName('order_id')]
        public string $orderId,
        #[Groups(['customer_pickup_session_confirm:read'])]
        #[SerializedName('order_status')]
        public string $orderStatus,
        #[Groups(['customer_pickup_session_confirm:read'])]
        #[SerializedName('scanned_at')]
        public string $scannedAt,
        #[Groups(['customer_pickup_session_confirm:read'])]
        #[SerializedName('merchant_confirmed_at')]
        public ?string $merchantConfirmedAt,
        #[Groups(['customer_pickup_session_confirm:read'])]
        #[SerializedName('customer_confirmed_at')]
        public ?string $customerConfirmedAt,
        #[Groups(['customer_pickup_session_confirm:read'])]
        #[SerializedName('is_used')]
        public bool $isUsed,
        #[Groups(['customer_pickup_session_confirm:read'])]
        #[SerializedName('is_completed')]
        public bool $isCompleted,
    ) {
    }
}
