<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\AdminBillingDocumentCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/billing-documents',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['billing_document_list:read']],
            provider: AdminBillingDocumentCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'page' => new QueryParameter(schema: ['type' => 'integer', 'default' => 1]),
                'limit' => new QueryParameter(schema: ['type' => 'integer', 'default' => 20]),
            ],
        ),
    ],
)]
final readonly class AdminBillingDocumentListOutput
{
    /**
     * @param list<BillingDocumentOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['billing_document_list:read'])]
        public array $items,
        #[Groups(['billing_document_list:read'])]
        public int $page,
        #[Groups(['billing_document_list:read'])]
        public int $limit,
        #[Groups(['billing_document_list:read'])]
        public int $total,
    ) {
    }
}
