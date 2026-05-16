<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Entity\Shop;
use App\Provider\MerchantPickupSlotRuleCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/pickup-slot-rules',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            provider: MerchantPickupSlotRuleCollectionProvider::class,
            normalizationContext: ['groups' => ['merchant_pickup_slot_rule_collection:read', 'merchant_pickup_slot_rule:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantPickupSlotRuleCollectionOutput
{
    /**
     * @param list<MerchantPickupSlotRuleOutput> $items
     */
    public function __construct(
        #[Groups(['merchant_pickup_slot_rule_collection:read'])]
        #[ApiProperty(identifier: true)]
        public int $total,
        #[Groups(['merchant_pickup_slot_rule_collection:read'])]
        public array $items,
    ) {
    }
}
