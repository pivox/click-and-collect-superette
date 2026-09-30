<?php

declare(strict_types=1);

namespace App\Mapper;

use App\ApiResource\StoreCatalogProductOutput;
use App\Entity\MerchantLocalProduct;
use App\Entity\MerchantProduct;
use App\Entity\ProductImage;
use App\Entity\ProductReference;
use App\Repository\ProductImageRepository;

/**
 * Maps a list of merchant products to catalog outputs with the images
 * batch-loaded (avoids N+1 on image lookups):
 * - referenced products expose the official (verified) referential image;
 * - local/vrac/reconditioned products expose the merchant's own photo
 *   (PRODUCT-IMAGE-003) — shop-scoped by construction, since a local product
 *   only ever appears in the catalog of its own supérette.
 */
final readonly class StoreCatalogProductBatchMapper
{
    public function __construct(
        private StoreCatalogProductMapper $storeCatalogProductMapper,
        private ProductImageRepository $productImageRepository,
    ) {
    }

    /**
     * @param list<MerchantProduct> $merchantProducts
     *
     * @return list<StoreCatalogProductOutput>
     */
    public function toOutputs(array $merchantProducts): array
    {
        $references = [];
        $localProducts = [];
        foreach ($merchantProducts as $merchantProduct) {
            $reference = $merchantProduct->getProductReference();
            if ($reference instanceof ProductReference) {
                $references[] = $reference;
                continue;
            }
            $localProduct = $merchantProduct->getLocalProduct();
            if ($localProduct instanceof MerchantLocalProduct) {
                $localProducts[] = $localProduct;
            }
        }
        $officialImages = $this->productImageRepository->findOfficialByProductReferences($references);
        $merchantPhotos = $this->productImageRepository->findCurrentByMerchantLocalProducts($localProducts);

        return array_map(
            function (MerchantProduct $merchantProduct) use ($officialImages, $merchantPhotos): StoreCatalogProductOutput {
                return $this->storeCatalogProductMapper->toOutput(
                    $merchantProduct,
                    $this->resolveImage($merchantProduct, $officialImages, $merchantPhotos),
                );
            },
            $merchantProducts,
        );
    }

    /**
     * @param array<string, ProductImage> $officialImages
     * @param array<string, ProductImage> $merchantPhotos
     */
    private function resolveImage(
        MerchantProduct $merchantProduct,
        array $officialImages,
        array $merchantPhotos,
    ): ?ProductImage {
        $reference = $merchantProduct->getProductReference();
        if (null !== $reference) {
            return $officialImages[$reference->getId()->toRfc4122()] ?? null;
        }

        $localProduct = $merchantProduct->getLocalProduct();
        if (null !== $localProduct) {
            return $merchantPhotos[$localProduct->getId()->toRfc4122()] ?? null;
        }

        return null;
    }
}
