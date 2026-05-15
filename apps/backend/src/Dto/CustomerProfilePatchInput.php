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

    #[Assert\Length(max: 20)]
    #[Assert\Regex('/^\+216[0-9]{8}$/')]
    public ?string $phone;

    public function __construct(
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $phone = null,
    ) {
        $this->firstName = $this->blankToNull($firstName);
        $this->lastName = $this->blankToNull($lastName);
        $this->phone = $this->blankToNull($phone);
    }

    private function blankToNull(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }
}
