<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Provider\AdminMerchantItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/merchants/{merchantId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'merchantId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_merchant:read']],
            provider: AdminMerchantItemProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminMerchantOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        public string $id,
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        public string $email,
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        #[SerializedName('first_name')]
        public ?string $firstName,
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        #[SerializedName('last_name')]
        public ?string $lastName,
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        public ?string $phone,
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        #[SerializedName('is_active')]
        public bool $isActive,
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        #[SerializedName('stores_count')]
        public int $storesCount,
    ) {
    }
}
