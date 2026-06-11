<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantCatalogListOutput;
use App\Mapper\MerchantCatalogProductMapper;
use App\Repository\MerchantProductRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantCatalogListOutput>
 */
final readonly class MerchantCatalogProductCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_LIMIT = 50;
    private const int MAX_LIMIT = 100;

    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantProductRepository $merchantProductRepository,
        private MerchantCatalogProductMapper $merchantCatalogProductMapper,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantCatalogListOutput
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
        $q = $request?->query->get('q') ?: null;
        $availability = $request?->query->get('availability') ?: null;
        $visibility = $request?->query->get('visibility') ?: null;
        $completion = $request?->query->get('completion') ?: null;
        $category = $request?->query->get('category') ?: null;
        $promotion = $request?->query->get('promotion') ?: null;
        $page = max(1, (int) ($request?->query->get('page') ?? 1));
        $limit = min(self::MAX_LIMIT, max(1, (int) ($request?->query->get('limit') ?? self::DEFAULT_LIMIT)));

        $allProducts = $this->merchantProductRepository->filterCatalogForShop(
            $shop,
            $q,
            $availability,
            $visibility,
            $completion,
            $category,
            $promotion,
        );

        $total = \count($allProducts);
        $offset = ($page - 1) * $limit;
        $pages = max(1, (int) ceil($total / $limit));

        $items = array_map(
            $this->merchantCatalogProductMapper->toOutput(...),
            array_values(\array_slice($allProducts, $offset, $limit)),
        );

        return new MerchantCatalogListOutput(
            id: $storeId,
            items: $items,
            total: $total,
            page: $page,
            limit: $limit,
            pages: $pages,
        );
    }
}
