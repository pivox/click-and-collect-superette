<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Entity\MerchantMembership;
use App\Entity\Shop;
use App\Entity\User;
use App\Provider\MerchantMeProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/me',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_me:read']],
            provider: MerchantMeProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantMeOutput
{
    public function __construct(
        #[Groups(['merchant_me:read'])]
        #[SerializedName('user_id')]
        public string $userId,
        #[Groups(['merchant_me:read'])]
        public string $email,
        #[Groups(['merchant_me:read'])]
        public string $name,
        #[Groups(['merchant_me:read'])]
        #[SerializedName('first_name')]
        public ?string $firstName,
        #[Groups(['merchant_me:read'])]
        #[SerializedName('last_name')]
        public ?string $lastName,
        #[Groups(['merchant_me:read'])]
        public ?string $phone,
        #[Groups(['merchant_me:read'])]
        public MerchantMeStoreOutput $store,
        #[Groups(['merchant_me:read'])]
        #[SerializedName('onboarding_completed')]
        public bool $onboardingCompleted,
        #[Groups(['merchant_me:read'])]
        #[SerializedName('password_change_required')]
        public bool $passwordChangeRequired,
        // MERCHANT-TEAM-003 additive fields: null while the account has not
        // been backfilled into an organization (excluded from JSON when null).
        #[Groups(['merchant_me:read'])]
        #[SerializedName('merchant_organization_id')]
        public ?string $merchantOrganizationId = null,
        /** @var array{status: string, is_primary: bool}|null */
        #[Groups(['merchant_me:read'])]
        public ?array $account = null,
    ) {
    }

    public static function fromUserAndShop(User $merchant, Shop $shop, ?MerchantMembership $membership = null): self
    {
        $organization = $membership?->getOrganization();

        return new self(
            userId: $merchant->getId()->toRfc4122(),
            email: $merchant->getEmail(),
            name: $merchant->getName(),
            firstName: $merchant->getFirstName(),
            lastName: $merchant->getLastName(),
            phone: $merchant->getPhone(),
            store: MerchantMeStoreOutput::fromShop($shop),
            onboardingCompleted: null !== $merchant->getOnboardingCompletedAt(),
            passwordChangeRequired: $merchant->isPasswordChangeRequired(),
            merchantOrganizationId: $organization?->getId()->toRfc4122(),
            account: null === $membership ? null : [
                'status' => $membership->getStatus()->value,
                'is_primary' => true === $organization?->getPrimaryAccount()?->getId()->equals($merchant->getId()),
            ],
        );
    }
}
