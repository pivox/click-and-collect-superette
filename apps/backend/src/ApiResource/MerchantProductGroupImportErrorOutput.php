<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class MerchantProductGroupImportErrorOutput
{
    public function __construct(
        #[Groups(['merchant_product_group_import:read'])]
        public string $productReferenceId,
        #[Groups(['merchant_product_group_import:read'])]
        public string $code,
        #[Groups(['merchant_product_group_import:read'])]
        public string $message,
    ) {
    }
}
