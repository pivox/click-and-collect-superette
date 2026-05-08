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

        return new MerchantCatalogProductOutput(
            id: $merchantProduct->getId()->toRfc4122(),
            productReferenceId: $productReference->getId()->toRfc4122(),
            nameFr: $productReference->getNameFr(),
            brand: $productReference->getBrand()->getCanonicalName(),
            category: $productReference->getCategory()->getNameFr(),
            volume: $productReference->getVolume(),
            unit: $productReference->getUnit()->value,
            priceTnd: $merchantProduct->getPriceTnd(),
            isAvailable: $merchantProduct->isAvailable(),
            isVisible: $merchantProduct->isVisible(),
            merchantNote: $merchantProduct->getMerchantNote(),
        );
    }
}
