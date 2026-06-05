<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Processor\AdminBillingDocumentWhatsappContactProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/billing-documents/{billingDocumentId<[0-9a-fA-F\-]{32,36}>}/whatsapp-contact',
            uriVariables: [
                'billingDocumentId' => new Link(fromClass: BillingDocumentOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: false,
            read: false,
            normalizationContext: ['groups' => ['billing_document_whatsapp:read']],
            processor: AdminBillingDocumentWhatsappContactProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminBillingDocumentWhatsappContactOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['billing_document_whatsapp:read'])]
        public string $id,
        #[Groups(['billing_document_whatsapp:read'])]
        #[SerializedName('billing_document_id')]
        public string $billingDocumentId,
        #[Groups(['billing_document_whatsapp:read'])]
        public string $phone,
        #[Groups(['billing_document_whatsapp:read'])]
        public string $message,
        #[Groups(['billing_document_whatsapp:read'])]
        #[SerializedName('whatsapp_url')]
        public string $whatsappUrl,
    ) {
    }
}
