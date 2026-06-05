<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\MerchantCatalogPhotoImportCommitInput;
use App\Dto\MerchantCatalogPhotoImportCommitItemInput;
use App\Entity\MerchantLocalProduct;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\MerchantProductPriceChangeType;
use App\Enum\MerchantProductPriceSource;
use App\Enum\ProductReferenceStatus;
use App\Repository\MerchantProductRepository;
use App\Repository\ProductReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MerchantCatalogPhotoImportCommitter
{
    public function __construct(
        private ProductReferenceRepository $productReferenceRepository,
        private MerchantProductRepository $merchantProductRepository,
        private MerchantProductPriceService $priceService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function commit(Shop $shop, MerchantCatalogPhotoImportCommitInput $input, ?User $changedByUser = null): MerchantCatalogCsvImportResult
    {
        $created = 0;
        $updated = 0;
        $ignored = 0;
        $items = [];
        $errors = [];

        $this->entityManager->wrapInTransaction(function () use ($shop, $input, $changedByUser, &$created, &$updated, &$ignored, &$items, &$errors): void {
            foreach ($input->items as $item) {
                if (!$item instanceof MerchantCatalogPhotoImportCommitItemInput) {
                    throw new \InvalidArgumentException('MerchantCatalogPhotoImportCommitItemInput expected.');
                }

                if (!$item->selected || 'already_in_catalog' === $item->status) {
                    ++$ignored;
                    continue;
                }

                $productReference = $this->findApprovedProductReference($item);
                if (null !== $item->productReferenceId && null === $productReference) {
                    $errors[] = new MerchantCatalogCsvParseError(
                        line: $item->line,
                        code: 'PRODUCT_REFERENCE_NOT_FOUND',
                        field: 'product_reference_id',
                        message: 'La référence produit est introuvable ou non approuvée.',
                    );
                    continue;
                }

                if (null === $productReference && !$this->canCreateLocalProduct($item)) {
                    $errors[] = new MerchantCatalogCsvParseError(
                        line: $item->line,
                        code: 'LOCAL_PRODUCT_FIELDS_REQUIRED',
                        field: null,
                        message: 'Le nom, la marque, le volume et l’unité sont obligatoires pour créer un produit local.',
                    );
                    continue;
                }

                [$merchantProduct, $status] = null !== $productReference
                    ? $this->upsertReferenceProduct($shop, $productReference, $item, $changedByUser)
                    : $this->upsertLocalProduct($shop, $item, $changedByUser);

                'created' === $status ? ++$created : ++$updated;
                $items[] = new MerchantCatalogCsvImportItem(
                    line: $item->line,
                    status: $status,
                    merchantProductId: $merchantProduct->getId()->toRfc4122(),
                    productReferenceId: $merchantProduct->getProductReference()?->getId()->toRfc4122(),
                    localProductId: $merchantProduct->getLocalProduct()?->getId()->toRfc4122(),
                    nameFr: $merchantProduct->getDisplayNameFr(),
                );
            }

            $this->entityManager->flush();
        });

        return new MerchantCatalogCsvImportResult(
            shopId: $shop->getId()->toRfc4122(),
            created: $created,
            updated: $updated,
            ignored: $ignored,
            items: $items,
            errors: $errors,
        );
    }

    private function findApprovedProductReference(MerchantCatalogPhotoImportCommitItemInput $item): ?ProductReference
    {
        if (null === $item->productReferenceId) {
            return null;
        }

        $productReference = $this->productReferenceRepository->find($item->productReferenceId);
        if (null === $productReference || ProductReferenceStatus::Approved !== $productReference->getStatus()) {
            return null;
        }

        return $productReference;
    }

    private function canCreateLocalProduct(MerchantCatalogPhotoImportCommitItemInput $item): bool
    {
        return null !== $this->normalizeOptionalText($item->brand)
            && null !== $this->normalizeDecimalText($item->volume)
            && null !== $item->unit;
    }

    /**
     * @return array{0: MerchantProduct, 1: 'created'|'updated'}
     */
    private function upsertReferenceProduct(
        Shop $shop,
        ProductReference $productReference,
        MerchantCatalogPhotoImportCommitItemInput $item,
        ?User $changedByUser,
    ): array {
        $merchantProduct = $this->merchantProductRepository->findOneForShopAndProductReference($shop, $productReference);
        if (null !== $merchantProduct) {
            $this->updateMerchantProduct($merchantProduct, $item, $changedByUser);

            return [$merchantProduct, 'updated'];
        }

        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd($this->normalizeDecimalText($item->priceTnd) ?? $item->priceTnd)
            ->setAvailable($item->isAvailable)
            ->setVisible($item->isVisible)
            ->setMerchantNote($this->normalizeOptionalText($item->merchantNote));

        $this->entityManager->persist($merchantProduct);
        $this->priceService->recordInitialPrice(
            merchantProduct: $merchantProduct,
            source: MerchantProductPriceSource::AiImport,
            changedByUser: $changedByUser,
        );

        return [$merchantProduct, 'created'];
    }

    /**
     * @return array{0: MerchantProduct, 1: 'created'|'updated'}
     */
    private function upsertLocalProduct(
        Shop $shop,
        MerchantCatalogPhotoImportCommitItemInput $item,
        ?User $changedByUser,
    ): array {
        $brand = $this->normalizeOptionalText($item->brand);
        $volume = $this->normalizeDecimalText($item->volume);
        if (null === $brand || null === $volume || null === $item->unit) {
            throw new \LogicException('Local product fields must be validated before upsert.');
        }

        $barcode = $this->normalizeOptionalText($item->barcode);
        $merchantProduct = null !== $barcode
            ? $this->merchantProductRepository->findOneLocalForShopAndBarcode($shop, $barcode)
            : $this->merchantProductRepository->findOneLocalForShopAndIdentity($shop, $brand, $item->nameFr, $volume, $item->unit);

        if (null !== $merchantProduct) {
            $localProduct = $merchantProduct->getLocalProduct();
            if (null === $localProduct) {
                throw new \LogicException('Expected a local merchant product.');
            }

            $this->updateLocalProduct($localProduct, $item, $brand, $volume, $barcode);
            $this->updateMerchantProduct($merchantProduct, $item, $changedByUser);

            return [$merchantProduct, 'updated'];
        }

        $localProduct = (new MerchantLocalProduct())
            ->setShop($shop);
        $this->updateLocalProduct($localProduct, $item, $brand, $volume, $barcode);

        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setLocalProduct($localProduct)
            ->setPriceTnd($this->normalizeDecimalText($item->priceTnd) ?? $item->priceTnd)
            ->setAvailable($item->isAvailable)
            ->setVisible($item->isVisible)
            ->setMerchantNote($this->normalizeOptionalText($item->merchantNote));

        $this->entityManager->persist($localProduct);
        $this->entityManager->persist($merchantProduct);
        $this->priceService->recordInitialPrice(
            merchantProduct: $merchantProduct,
            source: MerchantProductPriceSource::AiImport,
            changedByUser: $changedByUser,
        );

        return [$merchantProduct, 'created'];
    }

    private function updateLocalProduct(
        MerchantLocalProduct $localProduct,
        MerchantCatalogPhotoImportCommitItemInput $item,
        string $brand,
        string $volume,
        ?string $barcode,
    ): void {
        $localProduct
            ->setNameFr(trim($item->nameFr))
            ->setBrandName($brand)
            ->setVolume($volume)
            ->setUnit($item->unit ?? throw new \LogicException('Local product unit is required.'))
            ->setBarcode($barcode)
            ->setDefaultCategoryName($this->normalizeOptionalText($item->category))
            ->setPackQuantity($item->packQuantity);
    }

    private function updateMerchantProduct(
        MerchantProduct $merchantProduct,
        MerchantCatalogPhotoImportCommitItemInput $item,
        ?User $changedByUser,
    ): void {
        $this->priceService->changePrice(
            merchantProduct: $merchantProduct,
            newPrice: $this->normalizeDecimalText($item->priceTnd) ?? $item->priceTnd,
            changeType: MerchantProductPriceChangeType::ImportUpdate,
            source: MerchantProductPriceSource::AiImport,
            changedByUser: $changedByUser,
        );

        $merchantProduct
            ->setAvailable($item->isAvailable)
            ->setVisible($item->isVisible)
            ->setMerchantNote($this->normalizeOptionalText($item->merchantNote));
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private function normalizeDecimalText(?string $value): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        return bcadd(str_replace(',', '.', trim($value)), '0', 3);
    }
}
