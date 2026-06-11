<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ProductGroupVisibility;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminUpdateProductGroupInput
{
    #[Assert\Length(max: 160)]
    public ?string $nameFr = null;

    #[Assert\Length(max: 160)]
    public ?string $nameAr = null;

    #[Assert\Length(max: 180)]
    public ?string $slug = null;

    public ?string $descriptionFr = null;

    public ?string $descriptionAr = null;

    #[Assert\Length(max: 2)]
    public ?string $marketCountry = null;

    #[Assert\Choice(callback: [ProductGroupVisibility::class, 'values'])]
    public ?string $visibility = null;

    #[Assert\Length(max: 80)]
    public ?string $icon = null;

    #[Assert\Type('integer')]
    public mixed $sortOrder = null;
}
