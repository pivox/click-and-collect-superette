<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MerchantRedeemByCodeInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(exactly: 4)]
        #[Assert\Regex('/^\d{4}$/')]
        public string $pickupCode = '',
    ) {
    }
}
