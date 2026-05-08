<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
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
     *
     * @return list<ProductReferenceSearchOutput>
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

        $request = $this->requestStack->getCurrentRequest();
        $q = $request?->query->get('q');
        $brandId = $request?->query->get('brandId');
        $categorySlug = $request?->query->get('categorySlug');

        $references = $this->productReferenceRepository->search(
            null !== $q ? (string) $q : null,
            null !== $brandId ? (string) $brandId : null,
            null !== $categorySlug ? (string) $categorySlug : null,
        );

        return array_map(
            function (ProductReference $ref) use ($shop): ProductReferenceSearchOutput {
                $alreadyInCatalog = null !== $this->merchantProductRepository->findOneForShopAndProductReference($shop, $ref);

                return new ProductReferenceSearchOutput(
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
    }
}
