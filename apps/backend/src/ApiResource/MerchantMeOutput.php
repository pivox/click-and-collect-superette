<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
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
    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[Groups(['merchant_me:read'])]
        #[SerializedName('user_id')]
        public string $userId,
        #[Groups(['merchant_me:read'])]
        public string $email,
        #[Groups(['merchant_me:read'])]
        public array $roles,
        #[Groups(['merchant_me:read'])]
        public MerchantMeStoreOutput $store,
        #[Groups(['merchant_me:read'])]
        #[SerializedName('onboarding_completed')]
        public bool $onboardingCompleted,
    ) {
    }

    public static function fromUserAndShop(User $merchant, Shop $shop): self
    {
        return new self(
            userId: $merchant->getId()->toRfc4122(),
            email: $merchant->getEmail(),
            roles: self::merchantRoles($merchant),
            store: MerchantMeStoreOutput::fromShop($shop),
            onboardingCompleted: null !== $merchant->getOnboardingCompletedAt(),
        );
    }

    /**
     * @return list<string>
     */
    private static function merchantRoles(User $merchant): array
    {
        return array_values(array_filter(
            $merchant->getRoles(),
            static fn (string $role): bool => 'ROLE_USER' !== $role,
        ));
    }
}
