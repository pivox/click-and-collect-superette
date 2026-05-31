<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MerchantProductPriceUpdateInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex('/^\d{1,7}(?:\.\d{1,3})?$/')]
        #[Assert\Positive]
        public string $price,
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 3)]
        #[Assert\Regex('/^[A-Z]{3}$/i')]
        public string $currency = 'TND',
        #[Assert\Length(max: 500)]
        public ?string $reason = null,
    ) {
    }
}
