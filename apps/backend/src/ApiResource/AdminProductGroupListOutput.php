<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Dto\AdminCreateProductGroupInput;
use App\Processor\AdminCreateProductGroupProcessor;
use App\Provider\AdminProductGroupCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/product-groups',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_product_group_list:read']],
            provider: AdminProductGroupCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'q' => new QueryParameter(schema: ['type' => 'string'], description: 'Recherche dans le nom, slug ou description.'),
                'status' => new QueryParameter(schema: ['type' => 'string'], description: 'Statut (draft, published, archived).'),
                'market_country' => new QueryParameter(schema: ['type' => 'string'], description: 'Pays marché, TN par défaut.'),
                'page' => new QueryParameter(schema: ['type' => 'integer', 'default' => 1], description: 'Numéro de page.'),
                'limit' => new QueryParameter(schema: ['type' => 'integer', 'default' => 20], description: 'Résultats par page, max 50.'),
            ],
        ),
        new Post(
            uriTemplate: '/admin/product-groups',
            formats: ['json' => ['application/json']],
            input: AdminCreateProductGroupInput::class,
            output: AdminProductGroupOutput::class,
            normalizationContext: ['groups' => ['admin_product_group:read']],
            processor: AdminCreateProductGroupProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            status: 201,
            validate: true,
        ),
    ],
)]
final readonly class AdminProductGroupListOutput
{
    /**
     * @param list<AdminProductGroupOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_product_group_list:read'])]
        public array $items,
        #[Groups(['admin_product_group_list:read'])]
        public int $page,
        #[Groups(['admin_product_group_list:read'])]
        public int $limit,
        #[Groups(['admin_product_group_list:read'])]
        public int $total,
    ) {
    }
}
