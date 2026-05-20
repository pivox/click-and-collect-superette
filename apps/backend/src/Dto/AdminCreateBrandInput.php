<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminCreateBrandInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    public ?string $canonicalName = null;

    #[Assert\Length(max: 180)]
    public ?string $slug = null;

    /** @var list<string>|null */
    public ?array $aliases = null;

    #[Assert\Length(max: 2)]
    public ?string $country = null;
}
