<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantCatalogListOutput;
use App\Entity\MerchantLocalProduct;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Mapper\MerchantCatalogProductMapper;
use App\Repository\MerchantProductRepository;
use App\Repository\ProductImageRepository;
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
        private ProductImageRepository $productImageRepository,
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

        $pageProducts = array_values(\array_slice($allProducts, $offset, $limit));

        // PRODUCT-IMAGE-003: batch-load the images for the current page only —
        // official referential images + merchant local-product photos (no N+1).
        $references = [];
        $localProducts = [];
        foreach ($pageProducts as $merchantProduct) {
            $reference = $merchantProduct->getProductReference();
            if ($reference instanceof ProductReference) {
                $references[] = $reference;
                continue;
            }
            $localProduct = $merchantProduct->getLocalProduct();
            if ($localProduct instanceof MerchantLocalProduct) {
                $localProducts[] = $localProduct;
            }
        }
        $officialImages = $this->productImageRepository->findOfficialByProductReferences($references);
        $merchantPhotos = $this->productImageRepository->findCurrentByMerchantLocalProducts($localProducts);

        $items = array_map(
            function (MerchantProduct $merchantProduct) use ($officialImages, $merchantPhotos) {
                $reference = $merchantProduct->getProductReference();
                $localProduct = $merchantProduct->getLocalProduct();
                $image = null !== $reference
                    ? ($officialImages[$reference->getId()->toRfc4122()] ?? null)
                    : (null !== $localProduct ? ($merchantPhotos[$localProduct->getId()->toRfc4122()] ?? null) : null);

                return $this->merchantCatalogProductMapper->toOutput($merchantProduct, $image);
            },
            $pageProducts,
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
