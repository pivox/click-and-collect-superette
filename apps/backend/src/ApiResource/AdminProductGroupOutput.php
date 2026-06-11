<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\AdminCreateProductGroupItemInput;
use App\Dto\AdminUpdateProductGroupInput;
use App\Dto\AdminUpdateProductGroupItemInput;
use App\Processor\AdminArchiveProductGroupProcessor;
use App\Processor\AdminCreateProductGroupItemProcessor;
use App\Processor\AdminDeleteProductGroupItemProcessor;
use App\Processor\AdminPublishProductGroupProcessor;
use App\Processor\AdminUpdateProductGroupItemProcessor;
use App\Processor\AdminUpdateProductGroupProcessor;
use App\Provider\AdminProductGroupItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/product-groups/{groupId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'groupId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_product_group:read']],
            provider: AdminProductGroupItemProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/admin/product-groups/{groupId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'groupId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: AdminUpdateProductGroupInput::class,
            normalizationContext: ['groups' => ['admin_product_group:read']],
            provider: AdminProductGroupItemProvider::class,
            processor: AdminUpdateProductGroupProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            validate: true,
        ),
        new Patch(
            uriTemplate: '/admin/product-groups/{groupId<[0-9a-fA-F\-]{32,36}>}/publish',
            uriVariables: [
                'groupId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: false,
            normalizationContext: ['groups' => ['admin_product_group:read']],
            provider: AdminProductGroupItemProvider::class,
            processor: AdminPublishProductGroupProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/admin/product-groups/{groupId<[0-9a-fA-F\-]{32,36}>}/archive',
            uriVariables: [
                'groupId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: false,
            normalizationContext: ['groups' => ['admin_product_group:read']],
            provider: AdminProductGroupItemProvider::class,
            processor: AdminArchiveProductGroupProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/admin/product-groups/{groupId<[0-9a-fA-F\-]{32,36}>}/items',
            uriVariables: [
                'groupId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: AdminCreateProductGroupItemInput::class,
            output: AdminProductGroupItemOutput::class,
            read: false,
            normalizationContext: ['groups' => ['admin_product_group:read']],
            processor: AdminCreateProductGroupItemProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            status: 201,
            validate: true,
        ),
        new Patch(
            uriTemplate: '/admin/product-groups/{groupId<[0-9a-fA-F\-]{32,36}>}/items/{itemId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'groupId' => new Link(fromClass: self::class, identifiers: ['id']),
                'itemId' => new Link(fromClass: AdminProductGroupItemOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: AdminUpdateProductGroupItemInput::class,
            output: AdminProductGroupItemOutput::class,
            read: false,
            normalizationContext: ['groups' => ['admin_product_group:read']],
            processor: AdminUpdateProductGroupItemProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            validate: true,
        ),
        new Delete(
            uriTemplate: '/admin/product-groups/{groupId<[0-9a-fA-F\-]{32,36}>}/items/{itemId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'groupId' => new Link(fromClass: self::class, identifiers: ['id']),
                'itemId' => new Link(fromClass: AdminProductGroupItemOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            output: false,
            read: false,
            processor: AdminDeleteProductGroupItemProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            status: 204,
        ),
    ],
)]
final readonly class AdminProductGroupOutput
{
    /**
     * @param list<AdminProductGroupItemOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        public string $id,
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        public string $slug,
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        #[SerializedName('description_fr')]
        public ?string $descriptionFr,
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        #[SerializedName('description_ar')]
        public ?string $descriptionAr,
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        #[SerializedName('market_country')]
        public string $marketCountry,
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        public string $status,
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        public string $visibility,
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        public ?string $icon,
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        #[SerializedName('sort_order')]
        public int $sortOrder,
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        #[SerializedName('items_count')]
        public int $itemsCount,
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['admin_product_group:read', 'admin_product_group_list:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
        #[Groups(['admin_product_group:read'])]
        public array $items = [],
    ) {
    }
}
