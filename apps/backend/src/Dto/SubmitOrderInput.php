<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SubmitOrderInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        #[SerializedName('pickup_slot_id')]
        public string $pickupSlotId,
        #[Assert\Length(max: 500)]
        public ?string $notes = null,
    ) {
    }
}
