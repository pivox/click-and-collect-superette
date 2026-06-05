<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Provider\AdminBillingDocumentItemProvider;
use App\Provider\MerchantBillingDocumentItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/billing-documents/{billingDocumentId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'billingDocumentId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['billing_document:read']],
            provider: MerchantBillingDocumentItemProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Get(
            uriTemplate: '/admin/billing-documents/{billingDocumentId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'billingDocumentId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['billing_document:read']],
            provider: AdminBillingDocumentItemProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class BillingDocumentOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        public string $id,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('subscription_id')]
        public string $subscriptionId,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('merchant_id')]
        public string $merchantId,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('merchant_email')]
        public string $merchantEmail,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('document_number')]
        public string $documentNumber,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('document_type')]
        public string $documentType,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('document_nature_label')]
        public string $documentNatureLabel,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        public string $status,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('pricing_phase')]
        public string $pricingPhase,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        public string $currency,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('billing_period_start')]
        public string $billingPeriodStart,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('billing_period_end')]
        public string $billingPeriodEnd,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('issued_at')]
        public ?string $issuedAt,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('due_at')]
        public ?string $dueAt,
        #[Groups(['billing_document:read'])]
        #[SerializedName('paid_at')]
        public ?string $paidAt,
        #[Groups(['billing_document:read'])]
        #[SerializedName('cancelled_at')]
        public ?string $cancelledAt,
        #[Groups(['billing_document:read'])]
        #[SerializedName('cancellation_reason')]
        public ?string $cancellationReason,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('amount_tnd')]
        public string $amountTnd,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('amount_paid_tnd')]
        public string $amountPaidTnd,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('amount_due_tnd')]
        public string $amountDueTnd,
        #[Groups(['billing_document:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
    ) {
    }
}
