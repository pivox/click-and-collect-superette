<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AdminUpdateMerchantInput
{
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(min: 1, max: 100)]
    #[SerializedName('first_name')]
    public ?string $firstName;

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(min: 1, max: 100)]
    #[SerializedName('last_name')]
    public ?string $lastName;

    #[Assert\Length(max: 30)]
    public ?string $phone;

    #[SerializedName('is_active')]
    public ?bool $isActive;

    public function __construct(
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $phone = null,
        ?bool $isActive = null,
    ) {
        $this->firstName = null !== $firstName ? ('' !== trim($firstName) ? trim($firstName) : null) : null;
        $this->lastName = null !== $lastName ? ('' !== trim($lastName) ? trim($lastName) : null) : null;
        $this->phone = null !== $phone ? ('' !== trim($phone) ? trim($phone) : null) : null;
        $this->isActive = $isActive;
    }
}
