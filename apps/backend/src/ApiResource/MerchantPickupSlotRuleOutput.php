<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantPickupSlotRuleCreateInput;
use App\Dto\MerchantPickupSlotRulePatchInput;
use App\Entity\Shop;
use App\Processor\CreateMerchantPickupSlotRuleProcessor;
use App\Processor\DeleteMerchantPickupSlotRuleProcessor;
use App\Processor\UpdateMerchantPickupSlotRuleProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/pickup-slot-rules',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantPickupSlotRuleCreateInput::class,
            status: 201,
            read: false,
            processor: CreateMerchantPickupSlotRuleProcessor::class,
            normalizationContext: ['groups' => ['merchant_pickup_slot_rule:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Patch(
            uriTemplate: '/merchant/stores/{storeId}/pickup-slot-rules/{ruleId}',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'ruleId' => new Link(fromClass: MerchantPickupSlotRuleOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantPickupSlotRulePatchInput::class,
            read: false,
            processor: UpdateMerchantPickupSlotRuleProcessor::class,
            normalizationContext: ['groups' => ['merchant_pickup_slot_rule:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Delete(
            uriTemplate: '/merchant/stores/{storeId}/pickup-slot-rules/{ruleId}',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'ruleId' => new Link(fromClass: MerchantPickupSlotRuleOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            read: false,
            output: false,
            processor: DeleteMerchantPickupSlotRuleProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantPickupSlotRuleOutput
{
    public function __construct(
        #[Groups(['merchant_pickup_slot_rule:read'])]
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['merchant_pickup_slot_rule:read'])]
        public int $weekday,
        #[Groups(['merchant_pickup_slot_rule:read'])]
        #[SerializedName('start_time')]
        public string $startTime,
        #[Groups(['merchant_pickup_slot_rule:read'])]
        #[SerializedName('end_time')]
        public string $endTime,
        #[Groups(['merchant_pickup_slot_rule:read'])]
        public int $capacity,
        #[Groups(['merchant_pickup_slot_rule:read'])]
        #[SerializedName('is_active')]
        public bool $isActive,
    ) {
    }
}
