<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\AdminStoreCreateInput;
use App\Dto\AdminStoreUpdateInput;
use App\Processor\CreateAdminStoreProcessor;
use App\Processor\UpdateAdminStoreProcessor;
use App\Provider\AdminStoreItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/stores',
            formats: ['json' => ['application/json']],
            status: 201,
            normalizationContext: ['groups' => ['admin_store:read']],
            input: AdminStoreCreateInput::class,
            processor: CreateAdminStoreProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Get(
            uriTemplate: '/admin/stores/{storeId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'storeId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_store:read']],
            provider: AdminStoreItemProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/admin/stores/{storeId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'storeId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_store:read']],
            input: AdminStoreUpdateInput::class,
            processor: UpdateAdminStoreProcessor::class,
            read: false,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminStoreOutput
{
    /**
     * @param array<string, mixed>|null $openingHours
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_store:read', 'admin_store_list:read'])]
        public string $id,
        #[Groups(['admin_store:read', 'admin_store_list:read'])]
        public string $name,
        #[Groups(['admin_store:read', 'admin_store_list:read'])]
        public string $slug,
        #[Groups(['admin_store:read'])]
        public ?string $address,
        #[Groups(['admin_store:read', 'admin_store_list:read'])]
        public ?string $city,
        #[Groups(['admin_store:read'])]
        public ?string $phone,
        #[Groups(['admin_store:read', 'admin_store_list:read'])]
        #[SerializedName('is_active')]
        public bool $isActive,
        #[Groups(['admin_store:read', 'admin_store_list:read'])]
        #[SerializedName('qr_code_token')]
        public string $qrCodeToken,
        #[Groups(['admin_store:read', 'admin_store_list:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['admin_store:read', 'admin_store_list:read'])]
        public ?AdminStoreOwnerOutput $owner,
        #[Groups(['admin_store:read', 'admin_store_list:read'])]
        #[SerializedName('products_count')]
        public int $productsCount,
        #[Groups(['admin_store:read'])]
        #[SerializedName('theme_id')]
        public ?string $themeId,
        #[Groups(['admin_store:read'])]
        #[SerializedName('opening_hours')]
        public ?array $openingHours,
        #[Groups(['admin_store:read'])]
        #[SerializedName('exceptional_closures_count')]
        public int $exceptionalClosuresCount,
        #[Groups(['admin_store:read'])]
        #[SerializedName('pickup_rules_count')]
        public int $pickupRulesCount,
    ) {
    }
}
