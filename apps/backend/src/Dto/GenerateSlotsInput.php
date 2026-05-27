<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class GenerateSlotsInput
{
    public function __construct(
        #[Assert\Choice([1, 3])]
        #[SerializedName('horizon_months')]
        public int $horizonMonths = 1,
    ) {
    }
}
