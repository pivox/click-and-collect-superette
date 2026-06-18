<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminMerchantOnboardingFirstLoginOutput
{
    public function __construct(
        #[Groups(['admin_merchant_onboarding:read'])]
        public string $mode,
        #[Groups(['admin_merchant_onboarding:read'])]
        #[SerializedName('temporary_password')]
        public ?string $temporaryPassword = null,
    ) {
    }
}
