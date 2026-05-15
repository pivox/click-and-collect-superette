<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CustomerProfilePatchInput
{
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 100)]
    #[SerializedName('first_name')]
    public ?string $firstName;

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 100)]
    #[SerializedName('last_name')]
    public ?string $lastName;

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 200)]
    public ?string $name;

    #[Assert\Length(max: 20)]
    #[Assert\Regex('/^\+216[0-9]{8}$/')]
    public ?string $phone;

    public function __construct(
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $name = null,
        ?string $phone = null,
    ) {
        $this->firstName = $this->trimNullable($firstName);
        $this->lastName = $this->trimNullable($lastName);
        $this->name = $this->trimNullable($name);
        $this->phone = $this->trimNullable($phone);
    }

    private function trimNullable(?string $value): ?string
    {
        return null !== $value ? trim($value) : null;
    }
}
