<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantCatalogCsvImportErrorOutput;
use App\ApiResource\MerchantCatalogCsvImportItemOutput;
use App\ApiResource\MerchantCatalogCsvImportOutput;
use App\Dto\MerchantCatalogPhotoImportCommitInput;
use App\Entity\User;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\MerchantCatalogPhotoImportCommitter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantCatalogPhotoImportCommitInput, MerchantCatalogCsvImportOutput>
 */
final readonly class MerchantCatalogPhotoImportCommitProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private MerchantCatalogPhotoImportCommitter $committer,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantCatalogCsvImportOutput
    {
        if (!$data instanceof MerchantCatalogPhotoImportCommitInput) {
            throw new \InvalidArgumentException('MerchantCatalogPhotoImportCommitInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $user = $this->security->getUser();
        $result = $this->committer->commit(
            shop: $shop,
            input: $data,
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
