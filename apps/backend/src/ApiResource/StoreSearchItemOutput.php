<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class StoreSearchItemOutput
{
    public function __construct(
        #[Groups(['store_search:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['store_search:read'])]
        public string $name,
        #[Groups(['store_search:read'])]
        public string $slug,
        #[Groups(['store_search:read'])]
        public ?string $city,
        #[Groups(['store_search:read'])]
        public string $country,
        #[Groups(['store_search:read'])]
        #[SerializedName('is_active')]
        public bool $isActive,
    ) {
    }
}
