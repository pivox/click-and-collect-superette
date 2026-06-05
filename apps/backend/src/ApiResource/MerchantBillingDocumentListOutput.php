<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\MerchantBillingDocumentCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/billing-documents',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['billing_document_list:read']],
            provider: MerchantBillingDocumentCollectionProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
            parameters: [
                'page' => new QueryParameter(schema: ['type' => 'integer', 'default' => 1]),
                'limit' => new QueryParameter(schema: ['type' => 'integer', 'default' => 20]),
            ],
        ),
    ],
)]
final readonly class MerchantBillingDocumentListOutput
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
