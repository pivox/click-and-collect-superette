<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Entity\Shop;
use App\Processor\MerchantCatalogPhotoImportPreviewProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    shortName: 'CatalogPhotoImportPreviewOutput',
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/catalog/photo-import/preview',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            inputFormats: ['multipart' => ['multipart/form-data']],
            input: false,
            output: self::class,
            status: 200,
            read: false,
            deserialize: false,
            normalizationContext: ['groups' => ['merchant_catalog_photo_import:read']],
            processor: MerchantCatalogPhotoImportPreviewProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantCatalogPhotoImportPreviewOutput
{
    /**
     * @param list<MerchantCatalogPhotoImportPreviewItemOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: false)]
        #[Groups(['merchant_catalog_photo_import:read'])]
        public string $id,
        #[Groups(['merchant_catalog_photo_import:read'])]
        #[SerializedName('source_type')]
        public string $sourceType,
        #[Groups(['merchant_catalog_photo_import:read'])]
        #[SerializedName('detected_count')]
        public int $detectedCount,
        #[Groups(['merchant_catalog_photo_import:read'])]
        #[SerializedName('matched_reference_count')]
        public int $matchedReferenceCount,
        #[Groups(['merchant_catalog_photo_import:read'])]
        #[SerializedName('local_candidate_count')]
        public int $localCandidateCount,
        #[Groups(['merchant_catalog_photo_import:read'])]
        public array $items,
    ) {
    }
}
