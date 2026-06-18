<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminMerchantOnboardingMerchantInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    #[SerializedName('first_name')]
    public string $firstName = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    #[SerializedName('last_name')]
    public string $lastName = '';

    #[Assert\Length(max: 20)]
    public ?string $phone = null;
}
