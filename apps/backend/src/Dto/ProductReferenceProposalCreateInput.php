<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ProductUnit;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProductReferenceProposalCreateInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[SerializedName('name_ar')]
        public ?string $nameAr = null,
        #[Assert\Uuid]
        #[SerializedName('brand_id')]
        public ?string $brandId = null,
        #[Assert\Length(max: 160)]
        #[SerializedName('brand_name')]
        public ?string $brandName = null,
        #[Assert\NotBlank]
        #[Assert\Uuid]
        #[SerializedName('category_id')]
        public string $categoryId = '',
        #[Assert\Length(max: 160)]
        #[SerializedName('variant_fr')]
        public ?string $variantFr = null,
        #[Assert\Regex('/^\d{1,7}(?:\.\d{1,3})?$/')]
        public ?string $volume = null,
        public ProductUnit $unit = ProductUnit::Piece,
        #[Assert\Length(max: 64)]
        public ?string $barcode = null,
    ) {}
}
