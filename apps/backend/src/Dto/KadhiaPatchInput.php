<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class KadhiaPatchInput
{
    public function __construct(
        #[Assert\Length(max: 500)]
        public ?string $notes,
    ) {
    }
}
