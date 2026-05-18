<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AdminCreateMerchantInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    #[SerializedName('first_name')]
    public string $firstName;

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    #[SerializedName('last_name')]
    public string $lastName;

    #[Assert\Length(max: 30)]
    public ?string $phone;

    #[SerializedName('is_active')]
    public bool $isActive;

    public function __construct(
        string $email = '',
        string $firstName = '',
        string $lastName = '',
        ?string $phone = null,
        bool $isActive = true,
    ) {
        $this->email = trim($email);
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->phone = null !== $phone ? ('' !== trim($phone) ? trim($phone) : null) : null;
        $this->isActive = $isActive;
    }
}
