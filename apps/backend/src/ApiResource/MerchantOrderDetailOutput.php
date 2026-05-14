<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Entity\Order;
use App\Entity\Shop;
use App\Provider\MerchantOrderItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/orders/{orderId}',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'orderId' => new Link(fromClass: Order::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_order_detail:read'], 'skip_null_values' => false],
            provider: MerchantOrderItemProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantOrderDetailOutput
{
    /**
     * @param array{id: string, starts_at: string, ends_at: string}|null $pickupSlot
     * @param list<MerchantOrderLineOutput>                              $lines
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_order_detail:read'])]
        public string $id,
        #[Groups(['merchant_order_detail:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['merchant_order_detail:read'])]
        public string $status,
        #[Groups(['merchant_order_detail:read'])]
        #[SerializedName('total_tnd')]
        public string $totalTnd,
        #[Groups(['merchant_order_detail:read'])]
        #[SerializedName('pickup_slot')]
        public ?array $pickupSlot,
        #[Groups(['merchant_order_detail:read'])]
        public ?string $notes,
        #[Groups(['merchant_order_detail:read'])]
        public array $lines,
        #[Groups(['merchant_order_detail:read'])]
        #[SerializedName('customer_name')]
        public ?string $customerName,
        #[Groups(['merchant_order_detail:read'])]
        #[SerializedName('customer_phone')]
        public ?string $customerPhone,
        #[Groups(['merchant_order_detail:read'])]
        #[SerializedName('customer_email')]
        public ?string $customerEmail,
        #[Groups(['merchant_order_detail:read'])]
        #[SerializedName('rejection_reason')]
        public ?string $rejectionReason,
        #[Groups(['merchant_order_detail:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['merchant_order_detail:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
    ) {
    }
}
