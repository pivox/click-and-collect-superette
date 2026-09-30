<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\AdminProductImageCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Paginated admin listing of the product image provenance registry
 * (PRODUCT-IMAGE-004). Key use case: ?license=unknown lists every image whose
 * usage rights are still undocumented.
 *
 * S15-010 gotcha: no 'enum' in the QueryParameter schemas — the license and
 * status values are validated inside the provider (400 on invalid value).
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/product-images',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_product_image_list:read']],
            provider: AdminProductImageCollectionProvider::class,
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
                'license' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Filtre par code de licence (ex. unknown, platform_owned, cc_by…).',
                ),
                'status' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Filtre par statut d\'image (candidate, needs_review, verified, rejected, archived).',
                ),
            ],
        ),
    ],
)]
final readonly class AdminProductImageListOutput
{
    /**
     * @param list<ProductImageProvenanceOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_product_image_list:read'])]
        public array $items,
        #[Groups(['admin_product_image_list:read'])]
        public int $page,
        #[Groups(['admin_product_image_list:read'])]
        public int $limit,
        #[Groups(['admin_product_image_list:read'])]
        public int $total,
    ) {
    }
}
