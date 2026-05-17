<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class ShopOpeningHoursPatchInput
{
    /** @var array<string, mixed>|null */
    #[Assert\NotNull(message: 'OPENING_HOURS_REQUIRED')]
    #[Assert\Type(type: 'array', message: 'OPENING_HOURS_INVALID')]
    #[SerializedName('opening_hours')]
    public ?array $openingHours = null;
}
