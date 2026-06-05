<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Dto\AdminSubscriptionPaymentCreateInput;
use App\Processor\AdminCreateSubscriptionPaymentProcessor;
use App\Provider\AdminSubscriptionPaymentCollectionProvider;
use App\Provider\MerchantSubscriptionPaymentCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/subscription-payments',
            formats: ['json' => ['application/json']],
            input: AdminSubscriptionPaymentCreateInput::class,
            normalizationContext: ['groups' => ['subscription_payment:read']],
            processor: AdminCreateSubscriptionPaymentProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Get(
            uriTemplate: '/admin/subscription-payments',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['subscription_payment_list:read']],
            provider: AdminSubscriptionPaymentCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'page' => new QueryParameter(schema: ['type' => 'integer', 'default' => 1]),
                'limit' => new QueryParameter(schema: ['type' => 'integer', 'default' => 20]),
            ],
        ),
        new Get(
            uriTemplate: '/merchant/subscription-payments',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['subscription_payment_list:read']],
            provider: MerchantSubscriptionPaymentCollectionProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
            parameters: [
                'page' => new QueryParameter(schema: ['type' => 'integer', 'default' => 1]),
                'limit' => new QueryParameter(schema: ['type' => 'integer', 'default' => 20]),
            ],
        ),
    ],
)]
final readonly class SubscriptionPaymentOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        public string $id,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        #[SerializedName('billing_document_id')]
        public string $billingDocumentId,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        #[SerializedName('subscription_id')]
        public string $subscriptionId,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        #[SerializedName('merchant_id')]
        public string $merchantId,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        #[SerializedName('merchant_email')]
        public string $merchantEmail,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        #[SerializedName('amount_tnd')]
        public string $amountTnd,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        public string $currency,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        public string $method,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        public ?string $reference,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        public string $status,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        #[SerializedName('paid_at')]
        public string $paidAt,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        #[SerializedName('period_start')]
        public string $periodStart,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        #[SerializedName('period_end')]
        public string $periodEnd,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        #[SerializedName('validated_by_admin_id')]
        public string $validatedByAdminId,
        #[Groups(['subscription_payment:read', 'subscription_payment_list:read'])]
        #[SerializedName('validated_at')]
        public string $validatedAt,
    ) {
    }
}
