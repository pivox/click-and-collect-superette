<?php

declare(strict_types=1);

namespace App\Tests\Support\CatalogPhotoImport;

use App\Enum\ProductUnit;
use App\Service\MerchantCatalogPhotoDetectedProduct;
use App\Service\MerchantCatalogPhotoImportExtractorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TestMerchantCatalogPhotoImportExtractor implements MerchantCatalogPhotoImportExtractorInterface
{
    public function extract(UploadedFile $photo, string $sourceType): array
    {
        return [
            new MerchantCatalogPhotoDetectedProduct(
                line: 1,
                nameFr: 'Lait demi-écrémé',
                brand: 'Vitalait',
                volume: '1.000',
                unit: ProductUnit::Litre,
                barcode: '6191234567890',
                suggestedPriceTnd: '1.650',
                confidence: '0.940',
            ),
            new MerchantCatalogPhotoDetectedProduct(
                line: 2,
                nameFr: 'Harissa maison',
                brand: 'Jouda',
                volume: '350.000',
                unit: ProductUnit::Gramme,
                barcode: null,
                suggestedPriceTnd: '4.500',
                confidence: '0.820',
            ),
        ];
    }
}
