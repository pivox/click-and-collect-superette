<?php

declare(strict_types=1);

namespace App\Factory;

use App\ApiResource\MerchantProductPackOutput;
use App\ApiResource\ProductPackItemOutput;
use App\Entity\MerchantProduct;
use App\Entity\ProductPack;

final readonly class ProductPackOutputFactory
{
    public function toOutput(ProductPack $pack): MerchantProductPackOutput
    {
        $items = [];
        $totalPriceTnd = '0.000';

        $packItems = $pack->getItems()->toArray();
        usort($packItems, static fn ($a, $b) => $a->getId()->toRfc4122() <=> $b->getId()->toRfc4122());

        foreach ($packItems as $item) {
            $items[] = new ProductPackItemOutput(
                id: $item->getId()->toRfc4122(),
                merchantProductId: $item->getMerchantProduct()->getId()->toRfc4122(),
                nameFr: $this->getProductName($item->getMerchantProduct(), 'fr'),
                nameAr: $this->getProductName($item->getMerchantProduct(), 'ar'),
                quantity: $item->getQuantity(),
                unitPriceTnd: $item->getMerchantProduct()->getPriceTnd(),
            );

            $itemPrice = (string) bcmul($item->getMerchantProduct()->getPriceTnd(), (string) $item->getQuantity(), 3);
            $totalPriceTnd = (string) bcadd($totalPriceTnd, $itemPrice, 3);
        }

        return new MerchantProductPackOutput(
            id: $pack->getId()->toRfc4122(),
            storeId: $pack->getShop()->getId()->toRfc4122(),
            nameFr: $pack->getNameFr(),
            nameAr: $pack->getNameAr(),
            description: $pack->getDescription(),
            isActive: $pack->isActive(),
            items: $items,
            totalPriceTnd: $totalPriceTnd,
            createdAt: $pack->getCreatedAt()->format('c'),
            updatedAt: $pack->getUpdatedAt()->format('c'),
        );
    }

    private function getProductName(MerchantProduct $product, string $lang = 'fr'): string
    {
        if (null !== $product->getLocalProduct()) {
            $name = 'fr' === $lang ? $product->getLocalProduct()->getNameFr() : $product->getLocalProduct()->getNameAr();
            if (null === $name) {
                throw new \LogicException(\sprintf('LocalProduct %s has null name for language %s', $product->getLocalProduct()->getId()->toRfc4122(), $lang));
            }

            return $name;
        }

        if (null !== $product->getProductReference()) {
            $name = 'fr' === $lang ? $product->getProductReference()->getNameFr() : $product->getProductReference()->getNameAr();
            if (null === $name) {
                throw new \LogicException(\sprintf('ProductReference %s has null name for language %s', $product->getProductReference()->getId()->toRfc4122(), $lang));
            }

            return $name;
        }

        throw new \LogicException(\sprintf('MerchantProduct %s has neither LocalProduct nor ProductReference', $product->getId()->toRfc4122()));
    }
}
