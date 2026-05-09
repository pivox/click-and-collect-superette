<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ProductReferenceItemOutput;
use App\ApiResource\ProductReferenceSearchOutput;
use App\Entity\ProductReference;
use App\Repository\MerchantProductRepository;
use App\Repository\ProductReferenceRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<ProductReferenceSearchOutput>
 */
final readonly class ProductReferenceSearchProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private ProductReferenceRepository $productReferenceRepository,
        private MerchantProductRepository $merchantProductRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProductReferenceSearchOutput
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
        $q = $request?->query->get('q');
        $brandId = $request?->query->get('brandId');
        $categorySlug = $request?->query->get('categorySlug');

        $page = max(1, (int) ($request?->query->get('page') ?? 1));
        $limit = min(50, max(1, (int) ($request?->query->get('limit') ?? 20)));
        $offset = ($page - 1) * $limit;

        $queryStr = null !== $q ? (string) $q : null;
        $brandIdStr = null !== $brandId ? (string) $brandId : null;
        $categorySlugStr = null !== $categorySlug ? (string) $categorySlug : null;

        $references = $this->productReferenceRepository->search(
            $queryStr,
            $brandIdStr,
            $categorySlugStr,
            $limit,
            $offset,
        );

        $total = $this->productReferenceRepository->countSearch($queryStr, $brandIdStr, $categorySlugStr);

        $items = array_map(
            function (ProductReference $ref) use ($shop): ProductReferenceItemOutput {
                $alreadyInCatalog = null !== $this->merchantProductRepository->findOneForShopAndProductReference($shop, $ref);

                return new ProductReferenceItemOutput(
                    $ref->getId()->toRfc4122(),
                    $ref->getNameFr(),
                    $ref->getNameAr(),
                    $ref->getBrand()->getId()->toRfc4122(),
                    $ref->getBrand()->getCanonicalName(),
                    $ref->getCategory()->getId()->toRfc4122(),
                    $ref->getCategory()->getNameFr(),
                    $ref->getCategory()->getNameAr(),
                    $ref->getCategory()->getSlug(),
                    $ref->getVolume(),
                    $ref->getUnit()->value,
                    $ref->getBarcode(),
                    $alreadyInCatalog,
                );
            },
            $references,
        );

        return new ProductReferenceSearchOutput($items, $total, $page, $limit, $storeId);
    }
}
