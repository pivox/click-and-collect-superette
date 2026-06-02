<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\StoreCatalogCategoryOutput;
use App\ApiResource\StoreCatalogOutput;
use App\Entity\MerchantProduct;
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
        $page = max(1, $request?->query->getInt('page', 1) ?? 1);
        $itemsPerPage = min(100, max(1, $request?->query->getInt('items_per_page', 30) ?? 30));

        $catalog = $this->merchantProductRepository->findPublicCatalogForShop($shop, $query, $category);
        $total = \count($catalog);
        $pages = max(1, (int) ceil($total / $itemsPerPage));
        $page = min($page, $pages);
        $paginatedCatalog = \array_slice($catalog, ($page - 1) * $itemsPerPage, $itemsPerPage);

        $items = array_map(
            $this->storeCatalogProductMapper->toOutput(...),
            $paginatedCatalog,
        );

        return new StoreCatalogOutput(
            items: $items,
            categories: $this->buildCategories($catalog),
            page: $page,
            itemsPerPage: $itemsPerPage,
            total: $total,
            pages: $pages,
            storeId: $storeId,
        );
    }

    /**
     * @param list<MerchantProduct> $catalog
     *
     * @return list<StoreCatalogCategoryOutput>
     */
    private function buildCategories(array $catalog): array
    {
        $categories = [];
        foreach ($catalog as $merchantProduct) {
            $key = $merchantProduct->getDisplayCategorySlug();
            if (isset($categories[$key])) {
                continue;
            }

            $categories[$key] = new StoreCatalogCategoryOutput(
                key: $key,
                labelFr: $merchantProduct->getDisplayCategoryName(),
                labelAr: $merchantProduct->getDisplayCategoryNameAr(),
            );
        }

        uasort(
            $categories,
            static fn (StoreCatalogCategoryOutput $left, StoreCatalogCategoryOutput $right): int => $left->labelFr <=> $right->labelFr,
        );

        return array_values($categories);
    }
}
