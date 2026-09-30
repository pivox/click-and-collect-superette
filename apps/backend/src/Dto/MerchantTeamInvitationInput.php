<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Invitation payload for a secondary merchant account (MERCHANT-TEAM-004).
 */
final class MerchantTeamInvitationInput
{
    #[SerializedName('first_name')]
    #[Assert\NotBlank(message: 'MERCHANT_TEAM_FIRST_NAME_BLANK')]
    #[Assert\Length(max: 100)]
    public ?string $firstName = null;

    #[SerializedName('last_name')]
    #[Assert\NotBlank(message: 'MERCHANT_TEAM_LAST_NAME_BLANK')]
    #[Assert\Length(max: 100)]
    public ?string $lastName = null;

    #[Assert\NotBlank(message: 'MERCHANT_TEAM_EMAIL_BLANK')]
    #[Assert\Email(message: 'MERCHANT_TEAM_EMAIL_INVALID')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 20)]
    public ?string $phone = null;
}
