<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminCatalogPreloadErrorOutput
{
    public function __construct(
        #[Groups(['admin_merchant_onboarding:read'])]
        #[SerializedName('product_reference_id')]
        public string $productReferenceId,
        #[Groups(['admin_merchant_onboarding:read'])]
        public string $code,
        #[Groups(['admin_merchant_onboarding:read'])]
        public string $message,
        #[Groups(['admin_merchant_onboarding:read'])]
        #[SerializedName('product_group_id')]
        public ?string $productGroupId = null,
    ) {
    }
}
