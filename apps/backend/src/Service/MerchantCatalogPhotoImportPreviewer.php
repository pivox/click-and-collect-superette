<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Repository\MerchantProductRepository;
use App\Repository\ProductReferenceRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class MerchantCatalogPhotoImportPreviewer
{
    public function __construct(
        private MerchantCatalogPhotoImportExtractorInterface $extractor,
        private ProductReferenceRepository $productReferenceRepository,
        private MerchantProductRepository $merchantProductRepository,
    ) {
    }

    public function preview(Shop $shop, UploadedFile $photo, string $sourceType): MerchantCatalogPhotoImportPreviewResult
    {
        $detectedProducts = $this->extractor->extract($photo, $sourceType);
        $items = [];
        $matchedReferenceCount = 0;
        $localCandidateCount = 0;

        foreach ($detectedProducts as $product) {
            $reference = $this->matchReference($product);
            $localProduct = null === $reference ? $this->matchLocalMerchantProduct($shop, $product) : null;
            $alreadyInCatalog = null !== $localProduct
                || (null !== $reference && null !== $this->merchantProductRepository->findOneForShopAndProductReference($shop, $reference));
            $status = match (true) {
                $alreadyInCatalog => 'already_in_catalog',
                null !== $reference => 'matched_reference',
                default => 'local_candidate',
            };

            if ('matched_reference' === $status) {
                ++$matchedReferenceCount;
            }
            if ('local_candidate' === $status) {
                ++$localCandidateCount;
            }

            $items[] = new MerchantCatalogPhotoImportPreviewItem(
                line: $product->line,
                status: $status,
                productReferenceId: $reference?->getId()->toRfc4122(),
                nameFr: $reference?->getNameFr() ?? $localProduct?->getDisplayNameFr() ?? $product->nameFr,
                brand: $reference?->getBrand()->getCanonicalName() ?? $localProduct?->getDisplayBrandName() ?? $product->brand,
                volume: $reference?->getVolume() ?? $localProduct?->getDisplayVolume() ?? $product->volume,
                unit: $reference?->getUnit()->value ?? $localProduct?->getDisplayUnit()->value ?? $product->unit?->value,
                barcode: $reference?->getBarcode() ?? $localProduct?->getLocalProduct()?->getBarcode() ?? $product->barcode,
                suggestedPriceTnd: $product->suggestedPriceTnd,
                confidence: $product->confidence,
                alreadyInCatalog: $alreadyInCatalog,
            );
        }

        return new MerchantCatalogPhotoImportPreviewResult(
            shopId: $shop->getId()->toRfc4122(),
            sourceType: $sourceType,
            detectedCount: \count($detectedProducts),
            matchedReferenceCount: $matchedReferenceCount,
            localCandidateCount: $localCandidateCount,
            items: $items,
        );
    }

    private function matchReference(MerchantCatalogPhotoDetectedProduct $product): ?ProductReference
    {
        if (null !== $product->barcode) {
            $reference = $this->productReferenceRepository->findOneApprovedByBarcode($product->barcode);
            if (null !== $reference) {
                return $reference;
            }
        }

        if (null === $product->brand || null === $product->volume || null === $product->unit) {
            return null;
        }

        return $this->productReferenceRepository->findOneApprovedByIdentity(
            brandName: $product->brand,
            nameFr: $product->nameFr,
            volume: $product->volume,
            unit: $product->unit,
        );
    }

    private function matchLocalMerchantProduct(Shop $shop, MerchantCatalogPhotoDetectedProduct $product): ?MerchantProduct
    {
        if (null !== $product->barcode) {
            $merchantProduct = $this->merchantProductRepository->findOneLocalForShopAndBarcode($shop, $product->barcode);
            if (null !== $merchantProduct) {
                return $merchantProduct;
            }
        }

        if (null === $product->brand || null === $product->volume || null === $product->unit) {
            return null;
        }

        return $this->merchantProductRepository->findOneLocalForShopAndIdentity(
            shop: $shop,
            brandName: $product->brand,
            nameFr: $product->nameFr,
            volume: $product->volume,
            unit: $product->unit,
        );
    }
}
