<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MerchantCategoryCreateInput
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 160)]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Assert\Length(max: 160)]
        #[SerializedName('name_ar')]
        public ?string $nameAr = null,
        #[Assert\Uuid]
        #[SerializedName('parent_id')]
        public ?string $parentId = null,
        #[SerializedName('sort_order')]
        public int $sortOrder = 0,
        public bool $active = true,
    ) {
    }
}
