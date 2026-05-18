<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminCreateCategoryInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    public ?string $nameFr = null;

    #[Assert\Length(max: 160)]
    public ?string $nameAr = null;

    #[Assert\Length(max: 180)]
    public ?string $slug = null;
}
