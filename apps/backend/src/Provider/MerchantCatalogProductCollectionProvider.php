<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantCatalogProductOutput;
use App\Mapper\MerchantCatalogProductMapper;
use App\Repository\MerchantProductRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantCatalogProductOutput>
 */
final readonly class MerchantCatalogProductCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantProductRepository $merchantProductRepository,
        private MerchantCatalogProductMapper $merchantCatalogProductMapper,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<MerchantCatalogProductOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
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

        return array_map(
            $this->merchantCatalogProductMapper->toOutput(...),
            $this->merchantProductRepository->findCatalogForShop($shop),
        );
    }
}
