<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Dto\AdminCreateProductReferenceInput;
use App\Processor\AdminCreateProductReferenceProcessor;
use App\Provider\AdminProductReferenceCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/product-references',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_product_reference_list:read']],
            provider: AdminProductReferenceCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'q' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Recherche dans le nom FR, AR ou le code-barres.',
                ),
                'brand' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'UUID de la marque.',
                ),
                'category' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'UUID de la catégorie.',
                ),
                'status' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Statut (draft, pending_review, approved, rejected, archived).',
                ),
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 1],
                    description: 'Numéro de page (défaut : 1).',
                ),
                'limit' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 20],
                    description: 'Résultats par page (défaut : 20, max : 50).',
                ),
            ],
        ),
        new Post(
            uriTemplate: '/admin/product-references',
            formats: ['json' => ['application/json']],
            input: AdminCreateProductReferenceInput::class,
            output: AdminProductReferenceOutput::class,
            normalizationContext: ['groups' => ['admin_product_reference:read']],
            processor: AdminCreateProductReferenceProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            status: 201,
            validate: true,
        ),
    ],
)]
final readonly class AdminProductReferenceListOutput
{
    /**
     * @param list<AdminProductReferenceOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_product_reference_list:read'])]
        public array $items,
        #[Groups(['admin_product_reference_list:read'])]
        public int $page,
        #[Groups(['admin_product_reference_list:read'])]
        public int $limit,
        #[Groups(['admin_product_reference_list:read'])]
        public int $total,
    ) {
    }
}
