<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Processor\MerchantCompleteOnboardingProcessor;
use App\Provider\MerchantOnboardingProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/onboarding',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_onboarding:read']],
            provider: MerchantOnboardingProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Patch(
            uriTemplate: '/merchant/onboarding/complete',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_onboarding:read']],
            input: false,
            read: false,
            status: 200,
            processor: MerchantCompleteOnboardingProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantOnboardingOutput
{
    /**
     * @param list<MerchantOnboardingStepOutput> $steps
     */
    public function __construct(
        // Virtual identifier: the authenticated merchant's UUID. Not exposed in the URL.
        public string $id,
        #[Groups(['merchant_onboarding:read'])]
        public bool $completed,
        #[Groups(['merchant_onboarding:read'])]
        #[SerializedName('completed_at')]
        public ?string $completedAt,
        #[Groups(['merchant_onboarding:read'])]
        public array $steps,
    ) {
    }
}
