<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Entity\Shop;
use App\Processor\GenerateMerchantPickupSlotRulesProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/pickup-slot-rules/generate',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: false,
            status: 200,
            read: false,
            processor: GenerateMerchantPickupSlotRulesProcessor::class,
            normalizationContext: ['groups' => ['pickup_slot_rule_generation:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class PickupSlotRuleGenerationOutput
{
    public function __construct(
        #[Groups(['pickup_slot_rule_generation:read'])]
        #[ApiProperty(identifier: true)]
        #[SerializedName('generated_count')]
        public int $generatedCount,
        #[Groups(['pickup_slot_rule_generation:read'])]
        #[SerializedName('skipped_existing_count')]
        public int $skippedExistingCount,
        #[Groups(['pickup_slot_rule_generation:read'])]
        #[SerializedName('horizon_start')]
        public string $horizonStart,
        #[Groups(['pickup_slot_rule_generation:read'])]
        #[SerializedName('horizon_end')]
        public string $horizonEnd,
    ) {
    }
}
