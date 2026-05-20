<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Dto\AdminUpdateBrandInput;
use App\Processor\AdminDeleteBrandProcessor;
use App\Processor\AdminUpdateBrandProcessor;
use App\Provider\AdminBrandItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/brands/{brandId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'brandId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_brand:read']],
            provider: AdminBrandItemProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/admin/brands/{brandId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'brandId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: AdminUpdateBrandInput::class,
            normalizationContext: ['groups' => ['admin_brand:read']],
            provider: AdminBrandItemProvider::class,
            processor: AdminUpdateBrandProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            validate: true,
        ),
        new Delete(
            uriTemplate: '/admin/brands/{brandId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'brandId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            provider: AdminBrandItemProvider::class,
            processor: AdminDeleteBrandProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminBrandOutput
{
    /**
     * @param list<string> $aliases
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_brand:read', 'admin_brand_list:read'])]
        public string $id,
        #[Groups(['admin_brand:read', 'admin_brand_list:read'])]
        #[SerializedName('canonical_name')]
        public string $canonicalName,
        #[Groups(['admin_brand:read', 'admin_brand_list:read'])]
        public string $slug,
        #[Groups(['admin_brand:read', 'admin_brand_list:read'])]
        public array $aliases,
        #[Groups(['admin_brand:read', 'admin_brand_list:read'])]
        public ?string $country,
        #[Groups(['admin_brand:read', 'admin_brand_list:read'])]
        #[SerializedName('is_active')]
        public bool $isActive,
        #[Groups(['admin_brand:read', 'admin_brand_list:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['admin_brand:read', 'admin_brand_list:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
    ) {
    }
}
