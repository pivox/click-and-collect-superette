<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CustomerStoreFavoriteInput
{
    public function __construct(
        #[Assert\NotNull]
        #[SerializedName('is_favorite')]
        public bool $isFavorite,
    ) {
    }
}
