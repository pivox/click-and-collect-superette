<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Entity\Shop;
use App\Provider\PickupSlotCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new GetCollection(
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
final readonly class PickupSlotOutput
{
    public function __construct(
        #[Groups(['pickup_slot:read'])]
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['pickup_slot:read'])]
        #[SerializedName('starts_at')]
        public string $startsAt,
        #[Groups(['pickup_slot:read'])]
        #[SerializedName('ends_at')]
        public string $endsAt,
        #[Groups(['pickup_slot:read'])]
        public int $capacity,
        #[Groups(['pickup_slot:read'])]
        #[SerializedName('available_count')]
        public int $availableCount,
    ) {
    }
}
