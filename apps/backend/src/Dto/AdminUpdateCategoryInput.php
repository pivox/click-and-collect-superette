<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminUpdateCategoryInput
{
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 160)]
    public ?string $nameFr = null;

    #[Assert\Length(max: 160)]
    public ?string $nameAr = null;

    #[Assert\Type('bool')]
    public mixed $isActive = null;
}
