<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Partial update payload for the merchant-owned store profile.
 *
 * Every field is nullable so a PATCH can target a single property; the
 * processor distinguishes "absent" from "null" via the raw request payload.
 */
final class MerchantStoreProfileUpdateInput
{
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 160)]
    public ?string $name = null;

    #[Assert\Length(max: 255)]
    public ?string $address = null;

    #[Assert\Length(max: 100)]
    public ?string $city = null;

    #[Assert\Length(max: 20)]
    public ?string $phone = null;

    #[Assert\Url(requireTld: true, protocols: ['https', 'http'])]
    #[Assert\Length(max: 2048)]
    public ?string $logoUrl = null;

    #[Assert\Url(requireTld: true, protocols: ['https', 'http'])]
    #[Assert\Length(max: 2048)]
    public ?string $coverUrl = null;
}
