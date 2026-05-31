<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantProductPriceHistoryOutput;
use App\Entity\MerchantProduct;
use App\Mapper\MerchantProductPriceHistoryMapper;
use App\Repository\MerchantProductPriceHistoryRepository;
use App\Repository\MerchantProductRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantProductPriceHistoryOutput>
 */
final readonly class AdminMerchantProductPriceHistoryProvider implements ProviderInterface
{
    public function __construct(
        private MerchantProductRepository $merchantProductRepository,
        private MerchantProductPriceHistoryRepository $priceHistoryRepository,
        private MerchantProductPriceHistoryMapper $mapper,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantProductPriceHistoryOutput
    {
        $merchantProduct = $this->findMerchantProduct((string) ($uriVariables['merchantProductId'] ?? ''));

        return $this->mapper->toHistoryOutput(
            $merchantProduct,
            $this->priceHistoryRepository->findForMerchantProduct($merchantProduct),
        );
    }

    private function findMerchantProduct(string $merchantProductId): MerchantProduct
    {
        if (!Uuid::isValid($merchantProductId)) {
            throw new NotFoundHttpException('MERCHANT_PRODUCT_NOT_FOUND');
        }

        $merchantProduct = $this->merchantProductRepository->find($merchantProductId);
        if (null === $merchantProduct) {
            throw new NotFoundHttpException('MERCHANT_PRODUCT_NOT_FOUND');
        }

        return $merchantProduct;
    }
}
