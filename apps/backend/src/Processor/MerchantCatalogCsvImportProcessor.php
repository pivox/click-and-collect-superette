<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantCatalogCsvImportErrorOutput;
use App\ApiResource\MerchantCatalogCsvImportItemOutput;
use App\ApiResource\MerchantCatalogCsvImportOutput;
use App\Entity\User;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\MerchantCatalogCsvImporter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<null, MerchantCatalogCsvImportOutput>
 */
final readonly class MerchantCatalogCsvImportProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private MerchantCatalogCsvImporter $importer,
        private RequestStack $requestStack,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantCatalogCsvImportOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $request = $this->requestStack->getCurrentRequest();
        $user = $this->security->getUser();
        $result = $this->importer->import(
            shop: $shop,
            csv: $request?->getContent() ?? '',
            changedByUser: $user instanceof User ? $user : null,
        );

        return new MerchantCatalogCsvImportOutput(
            id: $result->shopId,
            created: $result->created,
            updated: $result->updated,
            ignored: $result->ignored,
            items: array_map(
                static fn ($item): MerchantCatalogCsvImportItemOutput => new MerchantCatalogCsvImportItemOutput(
                    line: $item->line,
                    status: $item->status,
                    merchantProductId: $item->merchantProductId,
                    productReferenceId: $item->productReferenceId,
                    localProductId: $item->localProductId,
                    nameFr: $item->nameFr,
                ),
                $result->items,
            ),
            errors: array_map(
                static fn ($error): MerchantCatalogCsvImportErrorOutput => new MerchantCatalogCsvImportErrorOutput(
                    line: $error->line,
                    code: $error->code,
                    field: $error->field,
                    message: $error->message,
                ),
                $result->errors,
            ),
        );
    }
}
