<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantProductBulkAvailabilityOutput;
use App\Dto\MerchantProductBulkAvailabilityInput;
use App\Repository\MerchantProductRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantProductBulkAvailabilityInput, MerchantProductBulkAvailabilityOutput>
 */
final readonly class MerchantProductBulkAvailabilityProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantProductRepository $merchantProductRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantProductBulkAvailabilityOutput
    {
        if (!$data instanceof MerchantProductBulkAvailabilityInput) {
            throw new \InvalidArgumentException('MerchantProductBulkAvailabilityInput expected.');
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

        $merchantProductIds = $this->uniqueMerchantProductIds($data->merchantProductIds);
        $merchantProducts = $this->merchantProductRepository->findForShopAndIds($shop, $merchantProductIds);

        if (\count($merchantProducts) !== \count($merchantProductIds)) {
            throw new NotFoundHttpException('MERCHANT_PRODUCT_NOT_FOUND');
        }

        $isAvailable = (bool) $data->isAvailable;
        $merchantNote = $this->normalizeMerchantNote($data->merchantNote);

        $this->entityManager->wrapInTransaction(function () use ($merchantProducts, $isAvailable, $merchantNote): void {
            foreach ($merchantProducts as $merchantProduct) {
                $merchantProduct
                    ->setAvailable($isAvailable)
                    ->setMerchantNote($merchantNote);
            }

            $this->entityManager->flush();
        });

        return new MerchantProductBulkAvailabilityOutput(
            id: $shop->getId()->toRfc4122(),
            updatedCount: \count($merchantProductIds),
            isAvailable: $isAvailable,
            merchantNote: $merchantNote,
            merchantProductIds: $merchantProductIds,
        );
    }

    /**
     * @param list<string> $merchantProductIds
     *
     * @return list<string>
     */
    private function uniqueMerchantProductIds(array $merchantProductIds): array
    {
        return array_values(array_unique($merchantProductIds));
    }

    private function normalizeMerchantNote(?string $merchantNote): ?string
    {
        if (null === $merchantNote) {
            return null;
        }

        $merchantNote = trim($merchantNote);

        return '' === $merchantNote ? null : $merchantNote;
    }
}
