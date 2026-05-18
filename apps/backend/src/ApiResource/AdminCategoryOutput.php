<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Dto\AdminUpdateCategoryInput;
use App\Processor\AdminDeleteCategoryProcessor;
use App\Processor\AdminUpdateCategoryProcessor;
use App\Provider\AdminCategoryItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/categories/{categoryId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'categoryId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_category:read']],
            provider: AdminCategoryItemProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/admin/categories/{categoryId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'categoryId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: AdminUpdateCategoryInput::class,
            normalizationContext: ['groups' => ['admin_category:read']],
            provider: AdminCategoryItemProvider::class,
            processor: AdminUpdateCategoryProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            validate: true,
        ),
        new Delete(
            uriTemplate: '/admin/categories/{categoryId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'categoryId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            provider: AdminCategoryItemProvider::class,
            processor: AdminDeleteCategoryProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminCategoryOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_category:read', 'admin_category_list:read'])]
        public string $id,
        #[Groups(['admin_category:read', 'admin_category_list:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['admin_category:read', 'admin_category_list:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['admin_category:read', 'admin_category_list:read'])]
        public string $slug,
        #[Groups(['admin_category:read', 'admin_category_list:read'])]
        #[SerializedName('is_active')]
        public bool $isActive,
        #[Groups(['admin_category:read', 'admin_category_list:read'])]
        #[SerializedName('sort_order')]
        public int $sortOrder,
        #[Groups(['admin_category:read', 'admin_category_list:read'])]
        #[SerializedName('parent_id')]
        public ?string $parentId,
        #[Groups(['admin_category:read', 'admin_category_list:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['admin_category:read', 'admin_category_list:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
    ) {
    }
}
