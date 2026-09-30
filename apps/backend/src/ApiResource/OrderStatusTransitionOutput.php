<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class OrderStatusTransitionOutput
{
    public function __construct(
        #[Groups(['order_status_history:read'])]
        public string $status,
        #[Groups(['order_status_history:read'])]
        public ?string $note,
        #[Groups(['order_status_history:read'])]
        #[SerializedName('at')]
        public string $at,
        // MERCHANT-TEAM-006 (merchant route only, additive): transition author.
        // The customer route keeps these null — staff names never leak to
        // customers.
        #[Groups(['order_status_history:read'])]
        #[SerializedName('actor_type')]
        public ?string $actorType = null,
        #[Groups(['order_status_history:read'])]
        #[SerializedName('actor_name')]
        public ?string $actorName = null,
    ) {
    }
}
