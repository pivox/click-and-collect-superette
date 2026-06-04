<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Partial update of the current merchant account (display name and email).
 *
 * Both fields are nullable so a PATCH can target a single property; the
 * processor distinguishes "absent" from "null" via the raw request payload.
 */
final class MerchantMeUpdateInput
{
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;
}
