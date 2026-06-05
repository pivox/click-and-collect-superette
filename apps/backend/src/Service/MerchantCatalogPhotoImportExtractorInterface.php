<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface MerchantCatalogPhotoImportExtractorInterface
{
    /**
     * @return list<MerchantCatalogPhotoDetectedProduct>
     */
    public function extract(UploadedFile $photo, string $sourceType): array;
}
