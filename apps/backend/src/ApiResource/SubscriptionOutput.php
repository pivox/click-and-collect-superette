<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Provider\AdminSubscriptionItemProvider;
use App\Provider\MerchantSubscriptionProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/subscription',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['subscription:read']],
            provider: MerchantSubscriptionProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Get(
            uriTemplate: '/admin/subscriptions/{subscriptionId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'subscriptionId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['subscription:read']],
            provider: AdminSubscriptionItemProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class SubscriptionOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['subscription:read', 'admin_subscription_list:read'])]
        public string $id,
        #[Groups(['subscription:read', 'admin_subscription_list:read'])]
        #[SerializedName('merchant_id')]
        public string $merchantId,
        #[Groups(['subscription:read', 'admin_subscription_list:read'])]
        #[SerializedName('merchant_email')]
        public string $merchantEmail,
        #[Groups(['subscription:read', 'admin_subscription_list:read'])]
        public string $lifecycle,
        #[Groups(['subscription:read', 'admin_subscription_list:read'])]
        #[SerializedName('pricing_phase')]
        public string $pricingPhase,
        #[Groups(['subscription:read', 'admin_subscription_list:read'])]
        #[SerializedName('monthly_price_tnd')]
        public string $monthlyPriceTnd,
        #[Groups(['subscription:read', 'admin_subscription_list:read'])]
        public string $currency,
        #[Groups(['subscription:read', 'admin_subscription_list:read'])]
        #[SerializedName('started_at')]
        public string $startedAt,
        #[Groups(['subscription:read', 'admin_subscription_list:read'])]
        #[SerializedName('current_period_started_at')]
        public string $currentPeriodStartedAt,
        #[Groups(['subscription:read', 'admin_subscription_list:read'])]
        #[SerializedName('current_period_ends_at')]
        public string $currentPeriodEndsAt,
        #[Groups(['subscription:read', 'admin_subscription_list:read'])]
        #[SerializedName('next_phase_change_at')]
        public ?string $nextPhaseChangeAt,
        #[Groups(['subscription:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
    ) {
    }
}
