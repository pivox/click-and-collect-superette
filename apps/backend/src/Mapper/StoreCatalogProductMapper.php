<?php

declare(strict_types=1);

namespace App\Mapper;

use App\ApiResource\StoreCatalogProductOutput;
use App\Entity\MerchantProduct;
use App\Entity\ProductImage;
use App\Service\ProductImage\ProductImageUrlBuilder;

final readonly class StoreCatalogProductMapper
{
    public function __construct(
        private ProductImageUrlBuilder $productImageUrlBuilder,
    ) {
    }

    /**
     * @param ?ProductImage $officialImage official image of the backing product reference, if any
     */
    public function toOutput(MerchantProduct $merchantProduct, ?ProductImage $officialImage = null): StoreCatalogProductOutput
    {
        $productReference = $merchantProduct->getProductReference();
        $localProduct = $merchantProduct->getLocalProduct();

        return new StoreCatalogProductOutput(
            id: $merchantProduct->getId()->toRfc4122(),
            productReferenceId: $productReference?->getId()->toRfc4122(),
            localProductId: $localProduct?->getId()->toRfc4122(),
            nameFr: $merchantProduct->getDisplayNameFr(),
            nameAr: $merchantProduct->getDisplayNameAr(),
            brand: $merchantProduct->getDisplayBrandName(),
            category: $merchantProduct->getDisplayCategoryName(),
            categoryAr: $merchantProduct->getDisplayCategoryNameAr(),
            categorySlug: $merchantProduct->getDisplayCategorySlug(),
            volume: $merchantProduct->getDisplayVolume(),
            unit: $merchantProduct->getDisplayUnit()->value,
            priceTnd: $merchantProduct->getPriceTnd(),
            promotionPriceTnd: $merchantProduct->getPromotionPriceTnd(),
            promotionEndsOn: $merchantProduct->getPromotionEndsOn()?->format('Y-m-d'),
            promotionActive: $merchantProduct->isPromotionActive(),
            effectivePriceTnd: $merchantProduct->getEffectivePriceTnd(),
            isAvailable: $merchantProduct->isAvailable(),
            image: $this->productImageUrlBuilder->build($officialImage),
        );
    }
}
