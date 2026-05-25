<?php

declare(strict_types=1);

namespace App\Mapper;

use App\ApiResource\StoreCatalogProductOutput;
use App\Entity\MerchantProduct;

final readonly class StoreCatalogProductMapper
{
    public function toOutput(MerchantProduct $merchantProduct): StoreCatalogProductOutput
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
            isAvailable: $merchantProduct->isAvailable(),
        );
    }
}
