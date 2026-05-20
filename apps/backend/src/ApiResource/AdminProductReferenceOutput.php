<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Dto\AdminUpdateProductReferenceInput;
use App\Processor\AdminArchiveProductReferenceProcessor;
use App\Processor\AdminUpdateProductReferenceProcessor;
use App\Provider\AdminProductReferenceItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/product-references/{productReferenceId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'productReferenceId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_product_reference:read']],
            provider: AdminProductReferenceItemProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/admin/product-references/{productReferenceId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'productReferenceId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: AdminUpdateProductReferenceInput::class,
            normalizationContext: ['groups' => ['admin_product_reference:read']],
            provider: AdminProductReferenceItemProvider::class,
            processor: AdminUpdateProductReferenceProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            validate: true,
        ),
        new Patch(
            uriTemplate: '/admin/product-references/{productReferenceId<[0-9a-fA-F\-]{32,36}>}/archive',
            uriVariables: [
                'productReferenceId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: false,
            normalizationContext: ['groups' => ['admin_product_reference:read']],
            provider: AdminProductReferenceItemProvider::class,
            processor: AdminArchiveProductReferenceProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminProductReferenceOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        public string $id,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('variant_fr')]
        public ?string $variantFr,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('variant_ar')]
        public ?string $variantAr,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('brand_id')]
        public string $brandId,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('brand_name')]
        public string $brandName,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('category_id')]
        public string $categoryId,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('category_name_fr')]
        public string $categoryNameFr,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('category_name_ar')]
        public ?string $categoryNameAr,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        public string $unit,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        public ?string $volume,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        public ?string $barcode,
        /** @var list<string> */
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        public array $aliases,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        public string $country,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        public string $status,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
    ) {
    }
}
