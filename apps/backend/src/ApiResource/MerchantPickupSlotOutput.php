<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantPickupSlotCreateInput;
use App\Dto\MerchantPickupSlotPatchInput;
use App\Entity\PickupSlot;
use App\Entity\Shop;
use App\Processor\CreateMerchantPickupSlotProcessor;
use App\Processor\DeleteMerchantPickupSlotProcessor;
use App\Processor\UpdateMerchantPickupSlotProcessor;
use App\Provider\MerchantPickupSlotCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/merchant/stores/{storeId}/pickup-slots',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            provider: MerchantPickupSlotCollectionProvider::class,
            normalizationContext: ['groups' => ['merchant_pickup_slot:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/pickup-slots',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantPickupSlotCreateInput::class,
            output: false,
            status: 201,
            read: false,
            processor: CreateMerchantPickupSlotProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Patch(
            uriTemplate: '/merchant/stores/{storeId}/pickup-slots/{slotId}',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'slotId' => new Link(fromClass: PickupSlot::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantPickupSlotPatchInput::class,
            output: false,
            status: 200,
            read: false,
            processor: UpdateMerchantPickupSlotProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Delete(
            uriTemplate: '/merchant/stores/{storeId}/pickup-slots/{slotId}',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'slotId' => new Link(fromClass: PickupSlot::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            read: false,
            output: false,
            processor: DeleteMerchantPickupSlotProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantPickupSlotOutput
{
    public function __construct(
        #[Groups(['merchant_pickup_slot:read'])]
        #[ApiProperty(identifier: false)]
        public string $id,
        #[Groups(['merchant_pickup_slot:read'])]
        #[SerializedName('starts_at')]
        public string $startsAt,
        #[Groups(['merchant_pickup_slot:read'])]
        #[SerializedName('ends_at')]
        public string $endsAt,
        #[Groups(['merchant_pickup_slot:read'])]
        public int $capacity,
        #[Groups(['merchant_pickup_slot:read'])]
        #[SerializedName('booked_count')]
        public int $bookedCount,
        #[Groups(['merchant_pickup_slot:read'])]
        #[SerializedName('is_active')]
        public bool $isActive,
    ) {
    }
}
