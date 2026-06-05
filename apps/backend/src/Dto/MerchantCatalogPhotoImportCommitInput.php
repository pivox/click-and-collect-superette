<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MerchantCatalogPhotoImportCommitInput
{
    /**
     * @param list<MerchantCatalogPhotoImportCommitItemInput> $items
     */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Count(min: 1, max: 50)]
        #[Assert\Valid]
        public array $items = [],
    ) {
    }
}
