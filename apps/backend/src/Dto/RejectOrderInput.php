<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RejectOrderInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 500)]
        public string $reason = '',
    ) {
    }
}
