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

        return new StoreCatalogProductOutput(
            id: $merchantProduct->getId()->toRfc4122(),
            productReferenceId: $productReference->getId()->toRfc4122(),
            nameFr: $productReference->getNameFr(),
            brand: $productReference->getBrand()->getCanonicalName(),
            category: $productReference->getCategory()->getNameFr(),
            volume: $productReference->getVolume(),
            unit: $productReference->getUnit()->value,
            priceTnd: $merchantProduct->getPriceTnd(),
            isAvailable: $merchantProduct->isAvailable(),
        );
    }
}
