<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class KadhiaListItemOutput
{
    public function __construct(
        #[Groups(['kadhia_list:read'])]
        public string $id,
        #[Groups(['kadhia_list:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['kadhia_list:read'])]
        #[SerializedName('store_name')]
        public string $storeName,
        #[Groups(['kadhia_list:read'])]
        public string $status,
        #[Groups(['kadhia_list:read'])]
        #[SerializedName('lines_count')]
        public int $linesCount,
        #[Groups(['kadhia_list:read'])]
        #[SerializedName('total_tnd')]
        public string $totalTnd,
        #[Groups(['kadhia_list:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['kadhia_list:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
    ) {
    }
}
