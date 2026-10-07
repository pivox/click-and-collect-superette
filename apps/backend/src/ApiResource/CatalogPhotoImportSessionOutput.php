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
use App\Dto\CreateCatalogPhotoImportSessionInput;
use App\Dto\UpdateCatalogPhotoImportSessionInput;
use App\Entity\CatalogPhotoImportImage;
use App\Entity\Shop;
use App\Processor\CancelCatalogPhotoImportSessionProcessor;
use App\Processor\CreateCatalogPhotoImportSessionProcessor;
use App\Processor\RegisterCatalogPhotoImportImageProcessor;
use App\Processor\RemoveCatalogPhotoImportImageProcessor;
use App\Processor\UpdateCatalogPhotoImportSessionProcessor;
use App\Provider\CatalogPhotoImportSessionItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * A merchant's photo-import session (CATALOG-AI-001, issue #638) — draft
 * persistence, shared quota reservation and shop-wide multi-account access.
 * Real file storage and AI dispatch land in CATALOG-AI-002/004 (#639/#641).
 */
#[ApiResource(
    shortName: 'CatalogPhotoImportSession',
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/catalog/photo-import/sessions/{sessionId}',
            uriVariables: [
                'storeId' => new Link(fromClass: self::class, identifiers: ['storeId']),
                'sessionId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            requirements: ['sessionId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['catalog_photo_import_session:read']],
            provider: CatalogPhotoImportSessionItemProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/catalog/photo-import/sessions',
            uriVariables: ['storeId' => new Link(fromClass: Shop::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            input: CreateCatalogPhotoImportSessionInput::class,
            status: 201,
            read: false,
            processor: CreateCatalogPhotoImportSessionProcessor::class,
            normalizationContext: ['groups' => ['catalog_photo_import_session:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Patch(
            uriTemplate: '/merchant/stores/{storeId}/catalog/photo-import/sessions/{sessionId}',
            uriVariables: [
                'storeId' => new Link(fromClass: self::class, identifiers: ['storeId']),
                'sessionId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            requirements: ['sessionId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
            formats: ['json' => ['application/json']],
            input: UpdateCatalogPhotoImportSessionInput::class,
            read: false,
            processor: UpdateCatalogPhotoImportSessionProcessor::class,
            normalizationContext: ['groups' => ['catalog_photo_import_session:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/catalog/photo-import/sessions/{sessionId}/cancel',
            uriVariables: [
                'storeId' => new Link(fromClass: self::class, identifiers: ['storeId']),
                'sessionId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            requirements: ['sessionId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
            formats: ['json' => ['application/json']],
            input: false,
            status: 200,
            read: false,
            processor: CancelCatalogPhotoImportSessionProcessor::class,
            normalizationContext: ['groups' => ['catalog_photo_import_session:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/catalog/photo-import/sessions/{sessionId}/images',
            uriVariables: [
                'storeId' => new Link(fromClass: self::class, identifiers: ['storeId']),
                'sessionId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            requirements: ['sessionId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
            formats: ['json' => ['application/json']],
            inputFormats: ['multipart' => ['multipart/form-data']],
            input: false,
            status: 201,
            read: false,
            deserialize: false,
            processor: RegisterCatalogPhotoImportImageProcessor::class,
            normalizationContext: ['groups' => ['catalog_photo_import_session:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Delete(
            uriTemplate: '/merchant/stores/{storeId}/catalog/photo-import/sessions/{sessionId}/images/{imageId}',
            uriVariables: [
                'storeId' => new Link(fromClass: self::class, identifiers: ['storeId']),
                'sessionId' => new Link(fromClass: self::class, identifiers: ['id']),
                'imageId' => new Link(fromClass: CatalogPhotoImportImage::class, identifiers: ['id']),
            ],
            requirements: [
                'sessionId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
                'imageId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
            ],
            formats: ['json' => ['application/json']],
            read: false,
            output: false,
            processor: RemoveCatalogPhotoImportImageProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class CatalogPhotoImportSessionOutput
{
    /**
     * @param list<CatalogPhotoImportSessionImageOutput> $images
     */
    public function __construct(
        #[Groups(['catalog_photo_import_session:read'])]
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['catalog_photo_import_session:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['catalog_photo_import_session:read'])]
        public string $status,
        #[Groups(['catalog_photo_import_session:read'])]
        public ?string $mode,
        #[Groups(['catalog_photo_import_session:read'])]
        #[SerializedName('campaign_id')]
        public ?string $campaignId,
        #[Groups(['catalog_photo_import_session:read'])]
        #[SerializedName('configuration_version')]
        public int $configurationVersion,
        #[Groups(['catalog_photo_import_session:read'])]
        public int $version,
        #[Groups(['catalog_photo_import_session:read'])]
        #[SerializedName('active_image_count')]
        public int $activeImageCount,
        #[Groups(['catalog_photo_import_session:read'])]
        public array $images,
        #[Groups(['catalog_photo_import_session:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['catalog_photo_import_session:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
        #[Groups(['catalog_photo_import_session:read'])]
        #[SerializedName('cancelled_at')]
        public ?string $cancelledAt,
    ) {
    }
}
