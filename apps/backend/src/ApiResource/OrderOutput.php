<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\SubmitOrderInput;
use App\Entity\Kadhia;
use App\Processor\SubmitOrderProcessor;
use App\Provider\OrderItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/orders/{id}',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['order:read']],
            provider: OrderItemProvider::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
        new Post(
            uriTemplate: '/me/kadhias/{kadhiaId}/submit',
            uriVariables: ['kadhiaId' => new Link(fromClass: Kadhia::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            input: SubmitOrderInput::class,
            normalizationContext: ['groups' => ['order:read']],
            status: 201,
            read: false,
            processor: SubmitOrderProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class OrderOutput
{
    /**
     * @param list<OrderLineOutput> $lines
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['order:read'])]
        public string $id,
        #[Groups(['order:read'])]
        #[SerializedName('kadhia_id')]
        public ?string $kadhiaId,
        #[Groups(['order:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['order:read'])]
        public string $status,
        #[Groups(['order:read'])]
        #[SerializedName('total_tnd')]
        public string $totalTnd,
        #[Groups(['order:read'])]
        #[SerializedName('pickup_slot_id')]
        public ?string $pickupSlotId,
        #[Groups(['order:read'])]
        public ?string $notes,
        #[Groups(['order:read'])]
        public array $lines,
        #[Groups(['order:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['order:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
    ) {
    }
}
