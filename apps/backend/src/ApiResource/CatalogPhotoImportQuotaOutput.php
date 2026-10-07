<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Entity\Shop;
use App\Provider\CatalogPhotoImportQuotaProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Merchant-facing balance of the shared photo-import quota
 * (CATALOG-AI-001, issue #638). Any active account of the shop's
 * organization sees the same numbers.
 */
#[ApiResource(
    shortName: 'CatalogPhotoImportQuota',
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/catalog/photo-import/quota',
            uriVariables: ['storeId' => new Link(fromClass: Shop::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['catalog_photo_import_quota:read']],
            provider: CatalogPhotoImportQuotaProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class CatalogPhotoImportQuotaOutput
{
    public function __construct(
        #[Groups(['catalog_photo_import_quota:read'])]
        #[ApiProperty(identifier: true)]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['catalog_photo_import_quota:read'])]
        public int $granted,
        #[Groups(['catalog_photo_import_quota:read'])]
        public int $reserved,
        #[Groups(['catalog_photo_import_quota:read'])]
        public int $consumed,
        #[Groups(['catalog_photo_import_quota:read'])]
        public int $available,
    ) {
    }
}
