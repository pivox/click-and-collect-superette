<?php

declare(strict_types=1);

namespace App\Service;

use App\ApiResource\MerchantProductGroupImportErrorOutput;
use App\ApiResource\MerchantProductGroupImportOutput;
use App\Dto\MerchantProductGroupImportInput;
use App\Entity\Shop;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class MerchantProductGroupImporter
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private ProductGroupCatalogImporter $productGroupCatalogImporter,
    ) {
    }

    public function import(string $storeId, MerchantProductGroupImportInput $input): MerchantProductGroupImportOutput
    {
        $shop = $this->getShop($storeId);
        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $result = $this->productGroupCatalogImporter->importSelectedGroupReferences(
            shop: $shop,
            groupId: $input->groupId,
            selectedProductReferenceIds: $input->selectedProductReferenceIds,
            defaultAvailability: (bool) $input->defaultAvailability,
        );

        return new MerchantProductGroupImportOutput(
            created: $result->created,
            alreadyInCatalog: $result->alreadyInCatalog,
            skipped: $result->skipped,
            requiresPriceCompletion: $result->requiresPriceCompletion,
            errors: array_map(
                static fn (ProductGroupCatalogImportError $error): MerchantProductGroupImportErrorOutput => new MerchantProductGroupImportErrorOutput(
                    productReferenceId: $error->productReferenceId,
                    code: $error->code,
                    message: $error->message,
                ),
                $result->errors,
            ),
        );
    }

    private function getShop(string $storeId): Shop
    {
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        return $shop;
    }
}
