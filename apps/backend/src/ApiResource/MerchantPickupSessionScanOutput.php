<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantPickupSessionScanInput;
use App\Processor\MerchantPickupSessionScanProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/pickup-sessions/scan',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_pickup_session_scan:read'], 'skip_null_values' => false],
            input: MerchantPickupSessionScanInput::class,
            status: 200,
            read: false,
            processor: MerchantPickupSessionScanProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantPickupSessionScanOutput
{
    /**
     * @param array{first_name: ?string, last_name: ?string, phone: ?string}                                $customer
     * @param list<array{merchant_product_id: string, name: string, quantity: int, unit_price_tnd: string}> $lines
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_pickup_session_scan:read'])]
        public string $id,
        #[Groups(['merchant_pickup_session_scan:read'])]
        #[SerializedName('order_id')]
        public string $orderId,
        #[Groups(['merchant_pickup_session_scan:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['merchant_pickup_session_scan:read'])]
        #[SerializedName('order_number')]
        public ?string $orderNumber,
        #[Groups(['merchant_pickup_session_scan:read'])]
        public string $status,
        #[Groups(['merchant_pickup_session_scan:read'])]
        #[SerializedName('scanned_at')]
        public string $scannedAt,
        #[Groups(['merchant_pickup_session_scan:read'])]
        public array $customer,
        #[Groups(['merchant_pickup_session_scan:read'])]
        public array $lines,
    ) {
    }
}
