<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\RejectOrderInput;
use App\Entity\Order;
use App\Entity\Shop;
use App\Processor\MerchantAcceptOrderProcessor;
use App\Processor\MerchantMarkReadyProcessor;
use App\Processor\MerchantRejectOrderProcessor;
use App\Processor\MerchantStartPreparationProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/orders/{orderId}/accept',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'orderId' => new Link(fromClass: Order::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_order:read']],
            input: false,
            status: 200,
            read: false,
            processor: MerchantAcceptOrderProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/orders/{orderId}/reject',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'orderId' => new Link(fromClass: Order::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_order:read']],
            input: RejectOrderInput::class,
            status: 200,
            read: false,
            processor: MerchantRejectOrderProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/orders/{orderId}/start-preparation',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'orderId' => new Link(fromClass: Order::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_order:read']],
            input: false,
            status: 200,
            read: false,
            processor: MerchantStartPreparationProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/orders/{orderId}/mark-ready',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'orderId' => new Link(fromClass: Order::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_order:read']],
            input: false,
            status: 200,
            read: false,
            processor: MerchantMarkReadyProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantOrderOutput
{
    /**
     * @param list<OrderLineOutput> $lines
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_order:read'])]
        public string $id,
        #[Groups(['merchant_order:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['merchant_order:read'])]
        public string $status,
        #[Groups(['merchant_order:read'])]
        #[SerializedName('total_tnd')]
        public string $totalTnd,
        #[Groups(['merchant_order:read'])]
        #[SerializedName('pickup_slot_id')]
        public ?string $pickupSlotId,
        #[Groups(['merchant_order:read'])]
        public ?string $notes,
        #[Groups(['merchant_order:read'])]
        public array $lines,
        #[Groups(['merchant_order:read'])]
        #[SerializedName('rejection_reason')]
        public ?string $rejectionReason,
        #[Groups(['merchant_order:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['merchant_order:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
    ) {
    }
}
