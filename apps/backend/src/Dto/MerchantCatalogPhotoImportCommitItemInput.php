<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ProductUnit;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MerchantCatalogPhotoImportCommitItemInput
{
    public function __construct(
        #[Assert\GreaterThanOrEqual(1)]
        public int $line = 1,
        public bool $selected = true,
        public ?string $status = null,
        #[Assert\Uuid]
        #[SerializedName('product_reference_id')]
        public ?string $productReferenceId = null,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        #[SerializedName('name_fr')]
        public string $nameFr = '',
        #[Assert\Length(max: 160)]
        public ?string $brand = null,
        #[Assert\Regex('/^\d{1,7}(?:\.\d{1,3})?$/')]
        public ?string $volume = null,
        public ?ProductUnit $unit = null,
        #[Assert\Length(max: 64)]
        #[Assert\Regex('/^[0-9]{8,14}$/')]
        public ?string $barcode = null,
        #[Assert\NotBlank]
        #[Assert\Regex('/^\d{1,7}(?:\.\d{1,3})?$/')]
        #[Assert\Positive]
        #[SerializedName('price_tnd')]
        public string $priceTnd = '',
        #[SerializedName('is_available')]
        public bool $isAvailable = true,
        #[SerializedName('is_visible')]
        public bool $isVisible = true,
        #[Assert\Length(max: 160)]
        public ?string $category = null,
        #[Assert\Length(max: 500)]
        #[SerializedName('merchant_note')]
        public ?string $merchantNote = null,
        #[Assert\GreaterThanOrEqual(1)]
        #[SerializedName('pack_quantity')]
        public int $packQuantity = 1,
    ) {
    }
}
