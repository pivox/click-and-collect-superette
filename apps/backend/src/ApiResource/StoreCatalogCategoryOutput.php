<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class StoreCatalogCategoryOutput
{
    public function __construct(
        #[Groups(['store_catalog:read'])]
        public string $key,
        #[Groups(['store_catalog:read'])]
        #[SerializedName('label_fr')]
        public string $labelFr,
        #[Groups(['store_catalog:read'])]
        #[SerializedName('label_ar')]
        public ?string $labelAr,
    ) {
    }
}
