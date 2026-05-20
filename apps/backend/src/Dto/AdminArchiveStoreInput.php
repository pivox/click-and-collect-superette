<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminArchiveStoreInput
{
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 500)]
    #[SerializedName('reason')]
    public ?string $reason = null;
}
