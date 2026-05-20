<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminStoreUpdateInput
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

    #[Assert\Type('bool')]
    public mixed $isActive = null;

    #[Assert\Uuid]
    public ?string $ownerId = null;

    #[Assert\Url(requireTld: true, protocols: ['https', 'http'])]
    #[Assert\Length(max: 2048)]
    public ?string $logoUrl = null;

    #[Assert\Url(requireTld: true, protocols: ['https', 'http'])]
    #[Assert\Length(max: 2048)]
    public ?string $coverUrl = null;
}
