<?php

declare(strict_types=1);

namespace App\Mapper;

use App\ApiResource\MerchantCatalogProductOutput;
use App\Entity\MerchantProduct;

final readonly class MerchantCatalogProductMapper
{
    public function toOutput(MerchantProduct $merchantProduct): MerchantCatalogProductOutput
    {
        $productReference = $merchantProduct->getProductReference();
        $localProduct = $merchantProduct->getLocalProduct();
        $merchantCategory = $merchantProduct->getActiveMerchantCategory();

        return new MerchantCatalogProductOutput(
            id: $merchantProduct->getId()->toRfc4122(),
            productReferenceId: $productReference?->getId()->toRfc4122(),
            localProductId: $localProduct?->getId()->toRfc4122(),
            merchantCategoryId: $merchantCategory?->getId()->toRfc4122(),
            merchantCategoryName: $merchantCategory?->getNameFr(),
            nameFr: $merchantProduct->getDisplayNameFr(),
            brand: $merchantProduct->getDisplayBrandName(),
            category: $merchantProduct->getDisplayCategoryName(),
            volume: $merchantProduct->getDisplayVolume(),
            unit: $merchantProduct->getDisplayUnit()->value,
            priceTnd: $merchantProduct->getPriceTnd(),
            promotionPriceTnd: $merchantProduct->getPromotionPriceTnd(),
            promotionEndsOn: $merchantProduct->getPromotionEndsOn()?->format('Y-m-d'),
            promotionActive: $merchantProduct->isPromotionActive(),
            effectivePriceTnd: $merchantProduct->getEffectivePriceTnd(),
            isAvailable: $merchantProduct->isAvailable(),
            isVisible: $merchantProduct->isVisible(),
            requiresPriceCompletion: 0 === bccomp($merchantProduct->getPriceTnd(), '0.000', 3),
            merchantNote: $merchantProduct->getMerchantNote(),
        );
    }
}
