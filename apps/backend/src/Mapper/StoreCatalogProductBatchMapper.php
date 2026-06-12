<?php

declare(strict_types=1);

namespace App\Mapper;

use App\ApiResource\StoreCatalogProductOutput;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Repository\ProductImageRepository;

/**
 * Maps a list of merchant products to catalog outputs with the official
 * images batch-loaded in a single query (avoids N+1 on image lookups).
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
        foreach ($merchantProducts as $merchantProduct) {
            $reference = $merchantProduct->getProductReference();
            if ($reference instanceof ProductReference) {
                $references[] = $reference;
            }
        }
        $officialImages = $this->productImageRepository->findOfficialByProductReferences($references);

        return array_map(
            function (MerchantProduct $merchantProduct) use ($officialImages): StoreCatalogProductOutput {
                $reference = $merchantProduct->getProductReference();
                $image = null !== $reference ? ($officialImages[$reference->getId()->toRfc4122()] ?? null) : null;

                return $this->storeCatalogProductMapper->toOutput($merchantProduct, $image);
            },
            $merchantProducts,
        );
    }
}
