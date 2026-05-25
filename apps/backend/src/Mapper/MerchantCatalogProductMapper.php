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

        return new MerchantCatalogProductOutput(
            id: $merchantProduct->getId()->toRfc4122(),
            productReferenceId: $productReference?->getId()->toRfc4122(),
            localProductId: $localProduct?->getId()->toRfc4122(),
            nameFr: $merchantProduct->getDisplayNameFr(),
            brand: $merchantProduct->getDisplayBrandName(),
            category: $merchantProduct->getDisplayCategoryName(),
            volume: $merchantProduct->getDisplayVolume(),
            unit: $merchantProduct->getDisplayUnit()->value,
            priceTnd: $merchantProduct->getPriceTnd(),
            isAvailable: $merchantProduct->isAvailable(),
            isVisible: $merchantProduct->isVisible(),
            merchantNote: $merchantProduct->getMerchantNote(),
        );
    }
}
