<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CustomerRegistrationInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    public string $password;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[SerializedName('first_name')]
    public string $firstName;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[SerializedName('last_name')]
    public string $lastName;

    #[Assert\Length(max: 20)]
    public ?string $phone;

    public function __construct(
        string $email = '',
        string $password = '',
        string $firstName = '',
        string $lastName = '',
        ?string $phone = null,
    ) {
        $this->email = trim($email);
        $this->password = $password;
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->phone = null !== $phone ? trim($phone) : null;
    }
}
