<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\StoreCatalogOutput;
use App\Mapper\StoreCatalogProductMapper;
use App\Repository\MerchantProductRepository;
use App\Repository\ShopRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<StoreCatalogOutput>
 */
final readonly class StoreCatalogProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantProductRepository $merchantProductRepository,
        private StoreCatalogProductMapper $storeCatalogProductMapper,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StoreCatalogOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop || !$shop->isActive()) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $request = $this->requestStack->getCurrentRequest();
        $query = $request?->query->getString('query') ?: null;
        $category = $request?->query->getString('category') ?: null;

        $items = array_map(
            $this->storeCatalogProductMapper->toOutput(...),
            $this->merchantProductRepository->findPublicCatalogForShop($shop, $query, $category),
        );

        return new StoreCatalogOutput($items, $storeId);
    }
}
