<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MerchantCatalogCreateInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        #[SerializedName('product_reference_id')]
        public string $productReferenceId,
        #[Assert\NotBlank]
        #[Assert\Regex('/^\d+(?:\.\d{1,3})?$/')]
        #[Assert\Positive]
        #[SerializedName('price_tnd')]
        public string $priceTnd,
        #[SerializedName('is_available')]
        public bool $isAvailable = true,
        #[SerializedName('is_visible')]
        public bool $isVisible = true,
        #[Assert\Length(max: 500)]
        #[SerializedName('merchant_note')]
        public ?string $merchantNote = null,
    ) {
    }
}
