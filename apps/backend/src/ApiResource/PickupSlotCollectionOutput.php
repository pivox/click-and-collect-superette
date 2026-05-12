<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Entity\Shop;
use App\Provider\PickupSlotCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/stores/{storeId}/pickup-slots',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            provider: PickupSlotCollectionProvider::class,
            normalizationContext: ['groups' => ['pickup_slot:read']],
            security: "is_granted('PUBLIC_ACCESS')",
        ),
    ],
)]
final readonly class PickupSlotCollectionOutput
{
    /**
     * @param list<PickupSlotOutput> $items
     */
    public function __construct(
        #[Groups(['pickup_slot:read'])]
        #[SerializedName('store_id')]
        #[ApiProperty(identifier: true)]
        public string $storeId,
        #[Groups(['pickup_slot:read'])]
        public array $items,
    ) {
    }
}
