<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ProductUnit;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MerchantLocalProductCreateInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Assert\Length(max: 255)]
        #[SerializedName('name_ar')]
        public ?string $nameAr = null,
        #[Assert\Length(max: 160)]
        #[SerializedName('brand_name')]
        public ?string $brandName = null,
        #[Assert\Regex('/^\d{1,7}(?:\.\d{1,3})?$/')]
        public ?string $volume = null,
        public ProductUnit $unit = ProductUnit::Piece,
        #[Assert\Length(max: 64)]
        public ?string $barcode = null,
        #[Assert\Length(max: 160)]
        #[SerializedName('default_category_name')]
        public ?string $defaultCategoryName = null,
        #[Assert\NotBlank]
        #[Assert\Regex('/^\d{1,7}(?:\.\d{1,3})?$/')]
        #[Assert\Positive]
        #[SerializedName('price_tnd')]
        public string $priceTnd = '',
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
