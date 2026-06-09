<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminRejectProductReferenceInput
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 500)]
    public ?string $rejection_reason = null;
}
