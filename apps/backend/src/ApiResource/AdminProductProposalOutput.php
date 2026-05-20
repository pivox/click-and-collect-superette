<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\QueryParameter;
use App\Dto\AdminApproveProductProposalInput;
use App\Dto\AdminRejectProductProposalInput;
use App\Processor\AdminApproveProductProposalProcessor;
use App\Processor\AdminRejectProductProposalProcessor;
use App\Provider\AdminProductProposalCollectionProvider;
use App\Provider\AdminProductProposalItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/product-proposals',
            formats: ['json' => ['application/json']],
            provider: AdminProductProposalCollectionProvider::class,
            normalizationContext: ['groups' => ['admin_proposal:read']],
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'status' => new QueryParameter(schema: ['type' => 'string'], description: 'Filtrer par statut (pending, approved, rejected, merged).'),
                'page' => new QueryParameter(schema: ['type' => 'integer']),
                'limit' => new QueryParameter(schema: ['type' => 'integer']),
            ],
        ),
        new Get(
            uriTemplate: '/admin/product-proposals/{proposalId}',
            uriVariables: [
                'proposalId' => new Link(fromClass: AdminProductProposalOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            provider: AdminProductProposalItemProvider::class,
            normalizationContext: ['groups' => ['admin_proposal:read']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/admin/product-proposals/{proposalId}/approve',
            uriVariables: [
                'proposalId' => new Link(fromClass: AdminProductProposalOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: AdminApproveProductProposalInput::class,
            output: false,
            status: 200,
            read: false,
            processor: AdminApproveProductProposalProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/admin/product-proposals/{proposalId}/reject',
            uriVariables: [
                'proposalId' => new Link(fromClass: AdminProductProposalOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: AdminRejectProductProposalInput::class,
            output: false,
            status: 200,
            read: false,
            processor: AdminRejectProductProposalProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminProductProposalOutput
{
    public function __construct(
        #[Groups(['admin_proposal:read'])]
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_proposal:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['admin_proposal:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['admin_proposal:read'])]
        #[SerializedName('brand_name')]
        public ?string $brandName,
        #[Groups(['admin_proposal:read'])]
        public string $category,
        #[Groups(['admin_proposal:read'])]
        public string $status,
        #[Groups(['admin_proposal:read'])]
        #[SerializedName('rejection_reason')]
        public ?string $rejectionReason,
        #[Groups(['admin_proposal:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['admin_proposal:read'])]
        #[SerializedName('proposed_by')]
        public string $proposedBy,
        #[Groups(['admin_proposal:read'])]
        #[SerializedName('created_product_reference_id')]
        public ?string $createdProductReferenceId = null,
    ) {
    }
}
