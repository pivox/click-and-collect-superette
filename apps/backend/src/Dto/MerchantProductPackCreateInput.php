<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MerchantProductPackCreateInput
{
    /**
     * @param ProductPackItemInput[] $items
     */
    public function __construct(
        #[SerializedName('name_fr')]
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $nameFr,

        #[SerializedName('name_ar')]
        #[Assert\Length(max: 255)]
        public ?string $nameAr = null,

        #[Assert\Length(max: 1000)]
        public ?string $description = null,

        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public array $items = [],
    ) {
    }
}
