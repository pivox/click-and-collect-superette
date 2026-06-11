<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProductPackItemInput
{
    public function __construct(
        #[SerializedName('merchant_product_id')]
        #[Assert\NotBlank]
        #[Assert\Uuid(strict: true)]
        public string $merchantProductId,

        #[Assert\GreaterThanOrEqual(1)]
        public int $quantity = 1,
    ) {
    }
}
