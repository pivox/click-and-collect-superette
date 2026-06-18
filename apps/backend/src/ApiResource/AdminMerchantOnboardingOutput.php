<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\AdminMerchantOnboardingInput;
use App\Processor\AdminMerchantOnboardingProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/merchant-onboarding',
            formats: ['json' => ['application/json']],
            input: AdminMerchantOnboardingInput::class,
            output: self::class,
            status: 201,
            read: false,
            normalizationContext: ['groups' => ['admin_merchant_onboarding:read', 'admin_merchant:read', 'admin_store:read']],
            processor: AdminMerchantOnboardingProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            validate: true,
        ),
    ],
)]
final readonly class AdminMerchantOnboardingOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_merchant_onboarding:read'])]
        public string $id,
        #[Groups(['admin_merchant_onboarding:read'])]
        public AdminMerchantOutput $merchant,
        #[Groups(['admin_merchant_onboarding:read'])]
        public AdminStoreOutput $shop,
        #[Groups(['admin_merchant_onboarding:read'])]
        #[SerializedName('first_login')]
        public AdminMerchantOnboardingFirstLoginOutput $firstLogin,
        #[Groups(['admin_merchant_onboarding:read'])]
        #[SerializedName('catalog_preload')]
        public AdminCatalogPreloadOutput $catalogPreload,
    ) {
    }
}
