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
    ) {
    }
}
