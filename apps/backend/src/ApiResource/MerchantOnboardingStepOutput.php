<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class MerchantOnboardingStepOutput
{
    public function __construct(
        #[Groups(['merchant_onboarding:read'])]
        public string $key,
        #[Groups(['merchant_onboarding:read'])]
        public string $label,
        #[Groups(['merchant_onboarding:read'])]
        public bool $completed,
    ) {
    }
}
