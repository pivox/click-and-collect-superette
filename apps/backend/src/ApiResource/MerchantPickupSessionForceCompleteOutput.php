<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Dto\MerchantPickupSessionForceCompleteInput;
use App\Processor\MerchantPickupSessionForceCompleteProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/merchant/pickup-sessions/{id}/force-complete',
            uriVariables: [
                'id' => new Link(fromClass: MerchantPickupSessionForceCompleteOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_pickup_session_force_complete:read'], 'skip_null_values' => false],
            input: MerchantPickupSessionForceCompleteInput::class,
            status: 200,
            read: false,
            processor: MerchantPickupSessionForceCompleteProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantPickupSessionForceCompleteOutput
{
    public function __construct(
        #[ApiProperty(identifier: false)]
        #[Groups(['merchant_pickup_session_force_complete:read'])]
        public string $id,
        #[Groups(['merchant_pickup_session_force_complete:read'])]
        #[SerializedName('order_id')]
        public string $orderId,
        #[Groups(['merchant_pickup_session_force_complete:read'])]
        #[SerializedName('order_status')]
        public string $orderStatus,
        #[Groups(['merchant_pickup_session_force_complete:read'])]
        #[SerializedName('scanned_at')]
        public string $scannedAt,
        #[Groups(['merchant_pickup_session_force_complete:read'])]
        #[SerializedName('merchant_confirmed_at')]
        public ?string $merchantConfirmedAt,
        #[Groups(['merchant_pickup_session_force_complete:read'])]
        #[SerializedName('customer_confirmed_at')]
        public ?string $customerConfirmedAt,
        #[Groups(['merchant_pickup_session_force_complete:read'])]
        #[SerializedName('is_used')]
        public bool $isUsed,
        #[Groups(['merchant_pickup_session_force_complete:read'])]
        #[SerializedName('is_completed')]
        public bool $isCompleted,
        #[Groups(['merchant_pickup_session_force_complete:read'])]
        #[SerializedName('force_completed_by_merchant')]
        public bool $forceCompletedByMerchant,
        #[Groups(['merchant_pickup_session_force_complete:read'])]
        #[SerializedName('force_note')]
        public ?string $forceNote,
    ) {
    }
}
