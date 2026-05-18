<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Dto\AdminCreateCategoryInput;
use App\Processor\AdminCreateCategoryProcessor;
use App\Provider\AdminCategoryCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/categories',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_category_list:read']],
            provider: AdminCategoryCollectionProvider::class,
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
            uriTemplate: '/admin/categories',
            formats: ['json' => ['application/json']],
            input: AdminCreateCategoryInput::class,
            output: AdminCategoryOutput::class,
            normalizationContext: ['groups' => ['admin_category:read']],
            processor: AdminCreateCategoryProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            status: 201,
            validate: true,
        ),
    ],
)]
final readonly class AdminCategoryListOutput
{
    /**
     * @param list<AdminCategoryOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_category_list:read'])]
        public array $items,
        #[Groups(['admin_category_list:read'])]
        public int $page,
        #[Groups(['admin_category_list:read'])]
        public int $limit,
        #[Groups(['admin_category_list:read'])]
        public int $total,
    ) {
    }
}
