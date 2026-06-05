<?php

declare(strict_types=1);

namespace App\Service;

final readonly class MerchantCatalogPhotoImportPreviewResult
{
    /**
     * @param list<MerchantCatalogPhotoImportPreviewItem> $items
     */
    public function __construct(
        public string $shopId,
        public string $sourceType,
        public int $detectedCount,
        public int $matchedReferenceCount,
        public int $localCandidateCount,
        public array $items,
    ) {
    }
}
