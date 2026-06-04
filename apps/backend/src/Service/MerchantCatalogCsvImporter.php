<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MerchantLocalProduct;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\MerchantProductPriceChangeType;
use App\Enum\MerchantProductPriceSource;
use App\Repository\MerchantProductRepository;
use App\Repository\ProductReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MerchantCatalogCsvImporter
{
    public function __construct(
        private MerchantCatalogCsvParser $parser,
        private ProductReferenceRepository $productReferenceRepository,
        private MerchantProductRepository $merchantProductRepository,
        private MerchantProductPriceService $priceService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function import(Shop $shop, string $csv, ?User $changedByUser = null): MerchantCatalogCsvImportResult
    {
        $parsed = $this->parser->parse($csv);
        $created = 0;
        $updated = 0;
        $items = [];

        $this->entityManager->wrapInTransaction(function () use ($shop, $parsed, $changedByUser, &$created, &$updated, &$items): void {
            foreach ($parsed->rows as $row) {
                $productReference = $this->findProductReference($row);
                if (null !== $productReference) {
                    $merchantProduct = $this->upsertReferenceProduct($shop, $productReference, $row, $changedByUser);
                } else {
                    $merchantProduct = $this->upsertLocalProduct($shop, $row, $changedByUser);
                }

                $isCreated = 'created' === $merchantProduct[1];
                $isCreated ? ++$created : ++$updated;
                $items[] = new MerchantCatalogCsvImportItem(
                    line: $row->line,
                    status: $merchantProduct[1],
                    merchantProductId: $merchantProduct[0]->getId()->toRfc4122(),
                    productReferenceId: $merchantProduct[0]->getProductReference()?->getId()->toRfc4122(),
                    localProductId: $merchantProduct[0]->getLocalProduct()?->getId()->toRfc4122(),
                    nameFr: $merchantProduct[0]->getDisplayNameFr(),
                );
            }

            $this->entityManager->flush();
        });

        return new MerchantCatalogCsvImportResult(
            shopId: $shop->getId()->toRfc4122(),
            created: $created,
            updated: $updated,
            ignored: $parsed->ignored,
            items: $items,
            errors: $parsed->errors,
        );
    }

    private function findProductReference(MerchantCatalogCsvParsedRow $row): ?ProductReference
    {
        if (null !== $row->barcode) {
            $byBarcode = $this->productReferenceRepository->findOneApprovedByBarcode($row->barcode);
            if (null !== $byBarcode) {
                return $byBarcode;
            }
        }

        return $this->productReferenceRepository->findOneApprovedByIdentity(
            brandName: $row->brand,
            nameFr: $row->nameFr,
            volume: $row->volume,
            unit: $row->unit,
        );
    }

    /**
     * @return array{0: MerchantProduct, 1: 'created'|'updated'}
     */
    private function upsertReferenceProduct(
        Shop $shop,
        ProductReference $productReference,
        MerchantCatalogCsvParsedRow $row,
        ?User $changedByUser,
    ): array {
        $merchantProduct = $this->merchantProductRepository->findOneForShopAndProductReference($shop, $productReference);
        if (null !== $merchantProduct) {
            $this->updateMerchantProduct($merchantProduct, $row, $changedByUser);

            return [$merchantProduct, 'updated'];
        }

        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd($row->priceTnd)
            ->setAvailable($row->isAvailable)
            ->setVisible($row->isVisible)
            ->setMerchantNote($row->merchantNote);

        $this->entityManager->persist($merchantProduct);
        $this->priceService->recordInitialPrice(
            merchantProduct: $merchantProduct,
            source: MerchantProductPriceSource::CatalogImport,
            changedByUser: $changedByUser,
        );

        return [$merchantProduct, 'created'];
    }

    /**
     * @return array{0: MerchantProduct, 1: 'created'|'updated'}
     */
    private function upsertLocalProduct(
        Shop $shop,
        MerchantCatalogCsvParsedRow $row,
        ?User $changedByUser,
    ): array {
        $merchantProduct = null !== $row->barcode
            ? $this->merchantProductRepository->findOneLocalForShopAndBarcode($shop, $row->barcode)
            : $this->merchantProductRepository->findOneLocalForShopAndIdentity($shop, $row->brand, $row->nameFr, $row->volume, $row->unit);

        if (null !== $merchantProduct) {
            $localProduct = $merchantProduct->getLocalProduct();
            if (null === $localProduct) {
                throw new \LogicException('Expected a local merchant product.');
            }

            $this->updateLocalProduct($localProduct, $row);
            $this->updateMerchantProduct($merchantProduct, $row, $changedByUser);

            return [$merchantProduct, 'updated'];
        }

        $localProduct = (new MerchantLocalProduct())
            ->setShop($shop);
        $this->updateLocalProduct($localProduct, $row);

        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setLocalProduct($localProduct)
            ->setPriceTnd($row->priceTnd)
            ->setAvailable($row->isAvailable)
            ->setVisible($row->isVisible)
            ->setMerchantNote($row->merchantNote);

        $this->entityManager->persist($localProduct);
        $this->entityManager->persist($merchantProduct);
        $this->priceService->recordInitialPrice(
            merchantProduct: $merchantProduct,
            source: MerchantProductPriceSource::CatalogImport,
            changedByUser: $changedByUser,
        );

        return [$merchantProduct, 'created'];
    }

    private function updateLocalProduct(MerchantLocalProduct $localProduct, MerchantCatalogCsvParsedRow $row): void
    {
        $localProduct
            ->setNameFr($row->nameFr)
            ->setNameAr($row->nameAr)
            ->setBrandName($row->brand)
            ->setVolume($row->volume)
            ->setUnit($row->unit)
            ->setBarcode($row->barcode)
            ->setDefaultCategoryName($row->category)
            ->setPackQuantity($row->packQuantity);
    }

    private function updateMerchantProduct(
        MerchantProduct $merchantProduct,
        MerchantCatalogCsvParsedRow $row,
        ?User $changedByUser,
    ): void {
        $this->priceService->changePrice(
            merchantProduct: $merchantProduct,
            newPrice: $row->priceTnd,
            changeType: MerchantProductPriceChangeType::ImportUpdate,
            source: MerchantProductPriceSource::CatalogImport,
            changedByUser: $changedByUser,
        );

        $merchantProduct
            ->setAvailable($row->isAvailable)
            ->setVisible($row->isVisible)
            ->setMerchantNote($row->merchantNote);
    }
}
