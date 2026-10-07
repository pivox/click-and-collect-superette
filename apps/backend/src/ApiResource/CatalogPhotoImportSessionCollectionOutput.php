<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Shop;
use App\Provider\CatalogPhotoImportSessionCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Paginated list of a shop's photo-import sessions (CATALOG-AI-001, #638).
 * Mirrors the manual-pagination convention of MerchantOrderHistoryOutput.
 */
#[ApiResource(
    shortName: 'CatalogPhotoImportSessionCollection',
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/catalog/photo-import/sessions',
            uriVariables: ['storeId' => new Link(fromClass: self::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['catalog_photo_import_session_collection:read', 'catalog_photo_import_session:read']],
            provider: CatalogPhotoImportSessionCollectionProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
            parameters: [
                'page' => new QueryParameter(schema: ['type' => 'integer', 'default' => 1]),
                'limit' => new QueryParameter(schema: ['type' => 'integer', 'default' => 20]),
            ],
        ),
    ],
)]
final readonly class CatalogPhotoImportSessionCollectionOutput
{
    /**
     * @param list<CatalogPhotoImportSessionOutput> $items
     */
    public function __construct(
        #[Groups(['catalog_photo_import_session_collection:read'])]
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['catalog_photo_import_session_collection:read'])]
        public array $items,
        #[Groups(['catalog_photo_import_session_collection:read'])]
        public int $page,
        #[Groups(['catalog_photo_import_session_collection:read'])]
        public int $limit,
        #[Groups(['catalog_photo_import_session_collection:read'])]
        public int $total,
    ) {
    }
}
