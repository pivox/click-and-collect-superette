<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Dto\AdminCreateBrandInput;
use App\Processor\AdminCreateBrandProcessor;
use App\Provider\AdminBrandCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/brands',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_brand_list:read']],
            provider: AdminBrandCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
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
            uriTemplate: '/admin/brands',
            formats: ['json' => ['application/json']],
            input: AdminCreateBrandInput::class,
            output: AdminBrandOutput::class,
            normalizationContext: ['groups' => ['admin_brand:read']],
            processor: AdminCreateBrandProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            status: 201,
            validate: true,
        ),
    ],
)]
final readonly class AdminBrandListOutput
{
    /**
     * @param list<AdminBrandOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_brand_list:read'])]
        public array $items,
        #[Groups(['admin_brand_list:read'])]
        public int $page,
        #[Groups(['admin_brand_list:read'])]
        public int $limit,
        #[Groups(['admin_brand_list:read'])]
        public int $total,
    ) {
    }
}
