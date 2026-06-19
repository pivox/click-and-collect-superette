<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminMerchantTemporaryPasswordOutput
{
    public function __construct(
        #[Groups(['admin_merchant_temporary_password:read'])]
        #[SerializedName('merchant_id')]
        public string $merchantId,
        #[Groups(['admin_merchant_temporary_password:read'])]
        #[SerializedName('temporary_password')]
        public string $temporaryPassword,
        #[Groups(['admin_merchant_temporary_password:read'])]
        #[SerializedName('expires_at')]
        public string $expiresAt,
    ) {
    }
}
