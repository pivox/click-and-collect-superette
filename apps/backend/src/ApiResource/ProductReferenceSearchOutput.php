<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Shop;
use App\Provider\ProductReferenceSearchProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/merchant/stores/{storeId}/product-references',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            provider: ProductReferenceSearchProvider::class,
            normalizationContext: ['groups' => ['product_reference_search:read']],
            security: "is_granted('ROLE_MERCHANT')",
            parameters: [
                'q' => new QueryParameter(schema: ['type' => 'string'], description: 'Recherche sur nom, marque, code-barres.'),
                'brandId' => new QueryParameter(schema: ['type' => 'string'], description: 'Filtrer par marque (UUID).'),
                'categorySlug' => new QueryParameter(schema: ['type' => 'string'], description: 'Filtrer par slug de catégorie.'),
            ],
        ),
    ],
)]
final readonly class ProductReferenceSearchOutput
{
    public function __construct(
        #[Groups(['product_reference_search:read'])]
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('brand_id')]
        public string $brandId,
        #[Groups(['product_reference_search:read'])]
        public string $brand,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('category_id')]
        public string $categoryId,
        #[Groups(['product_reference_search:read'])]
        public string $category,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('category_ar')]
        public ?string $categoryAr,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('category_slug')]
        public string $categorySlug,
        #[Groups(['product_reference_search:read'])]
        public ?string $volume,
        #[Groups(['product_reference_search:read'])]
        public string $unit,
        #[Groups(['product_reference_search:read'])]
        public ?string $barcode,
        #[Groups(['product_reference_search:read'])]
        #[SerializedName('already_in_catalog')]
        public bool $alreadyInCatalog,
    ) {}
}
