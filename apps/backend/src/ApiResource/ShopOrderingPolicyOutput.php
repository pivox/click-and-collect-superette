<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Dto\ShopOrderingPolicyPatchInput;
use App\Entity\Shop;
use App\Entity\ShopOrderingPolicy;
use App\Processor\UpdateMerchantShopOrderingPolicyProcessor;
use App\Provider\MerchantShopOrderingPolicyProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Per-shop ordering policy exposed to the owning merchant (ORDER-LEAD-001).
 *
 * Historical shops have no persisted policy: reads then resolve the default
 * minimum lead time (0) with a null updated_at. The PATCH upserts the row.
 * Slot listing/submission enforcement is delivered separately (ORDER-LEAD-002).
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/ordering-policy',
            formats: ['json' => ['application/json']],
            provider: MerchantShopOrderingPolicyProvider::class,
            normalizationContext: ['groups' => ['shop_ordering_policy:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Patch(
            uriTemplate: '/merchant/stores/{storeId}/ordering-policy',
            formats: ['json' => ['application/json']],
            input: ShopOrderingPolicyPatchInput::class,
            output: self::class,
            read: false,
            processor: UpdateMerchantShopOrderingPolicyProcessor::class,
            normalizationContext: ['groups' => ['shop_ordering_policy:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class ShopOrderingPolicyOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['shop_ordering_policy:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['shop_ordering_policy:read'])]
        #[SerializedName('minimum_pickup_lead_time_minutes')]
        public int $minimumPickupLeadTimeMinutes,
        #[Groups(['shop_ordering_policy:read'])]
        #[SerializedName('updated_at')]
        public ?string $updatedAt,
    ) {
    }

    public static function fromShop(Shop $shop, ?ShopOrderingPolicy $policy): self
    {
        return new self(
            storeId: $shop->getId()->toRfc4122(),
            minimumPickupLeadTimeMinutes: null === $policy
                ? ShopOrderingPolicy::DEFAULT_MINIMUM_PICKUP_LEAD_TIME_MINUTES
                : $policy->getMinimumPickupLeadTimeMinutes(),
            updatedAt: $policy?->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
