<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantCatalogPhotoImportCommitInput;
use App\Entity\Shop;
use App\Processor\MerchantCatalogCsvImportProcessor;
use App\Processor\MerchantCatalogPhotoImportCommitProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'CsvImportOutput',
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/catalog/import-csv',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            inputFormats: ['csv' => ['text/csv', 'text/plain']],
            input: false,
            output: self::class,
            status: 200,
            read: false,
            deserialize: false,
            normalizationContext: ['groups' => ['merchant_catalog_import:read']],
            processor: MerchantCatalogCsvImportProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/catalog/photo-import/commit',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantCatalogPhotoImportCommitInput::class,
            output: self::class,
            status: 200,
            read: false,
            normalizationContext: ['groups' => ['merchant_catalog_import:read']],
            processor: MerchantCatalogPhotoImportCommitProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantCatalogCsvImportOutput
{
    /**
     * @param list<MerchantCatalogCsvImportItemOutput>  $items
     * @param list<MerchantCatalogCsvImportErrorOutput> $errors
     */
    public function __construct(
        #[ApiProperty(identifier: false)]
        #[Groups(['merchant_catalog_import:read'])]
        public string $id,
        #[Groups(['merchant_catalog_import:read'])]
        public int $created,
        #[Groups(['merchant_catalog_import:read'])]
        public int $updated,
        #[Groups(['merchant_catalog_import:read'])]
        public int $ignored,
        #[Groups(['merchant_catalog_import:read'])]
        public array $items,
        #[Groups(['merchant_catalog_import:read'])]
        public array $errors,
    ) {
    }
}
