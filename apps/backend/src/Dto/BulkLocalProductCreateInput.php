<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class BulkLocalProductCreateInput
{
    /**
     * @param list<BulkLocalProductFormatInput> $formats
     */
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        #[SerializedName('base_name_fr')]
        public string $baseNameFr,
        #[Assert\Length(max: 255)]
        #[SerializedName('base_name_ar')]
        public ?string $baseNameAr = null,
        #[Assert\Length(max: 160)]
        #[SerializedName('brand_name')]
        public ?string $brandName = null,
        #[Assert\Length(max: 160)]
        #[SerializedName('default_category_name')]
        public ?string $defaultCategoryName = null,
        #[Assert\Uuid]
        #[SerializedName('merchant_category_id')]
        public ?string $merchantCategoryId = null,
        #[Assert\NotNull]
        #[Assert\Count(min: 1, max: 20)]
        #[Assert\Valid]
        public array $formats = [],
    ) {
    }
}
