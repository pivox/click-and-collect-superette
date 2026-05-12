<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\CustomerShopSource;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CustomerStoreVisitInput
{
    public function __construct(
        #[Assert\NotNull]
        public CustomerShopSource $source,
    ) {
    }
}
