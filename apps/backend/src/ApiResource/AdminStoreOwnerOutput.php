<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class AdminStoreOwnerOutput
{
    public function __construct(
        #[Groups(['admin_store:read', 'admin_store_list:read'])]
        public string $id,
        #[Groups(['admin_store:read', 'admin_store_list:read'])]
        public string $email,
    ) {
    }
}
