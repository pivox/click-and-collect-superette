<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CustomerFavoriteProductListOutput;
use App\Entity\User;
use App\Mapper\StoreCatalogProductBatchMapper;
use App\Repository\CustomerProductFavoriteRepository;
use App\Repository\ShopRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<CustomerFavoriteProductListOutput>
 */
final readonly class CustomerFavoriteProductCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private CustomerProductFavoriteRepository $customerProductFavoriteRepository,
        private StoreCatalogProductBatchMapper $storeCatalogProductBatchMapper,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomerFavoriteProductListOutput
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop || !$shop->isActive()) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $favorites = $this->customerProductFavoriteRepository->findByCustomerAndShop($user, $shop);

        // Unavailable products stay listed (the frontend shows "rupture");
        // hidden products are excluded so the list never leaks them.
        $merchantProducts = [];
        foreach ($favorites as $favorite) {
            $merchantProduct = $favorite->getMerchantProduct();
            if ($merchantProduct->isVisible()) {
                $merchantProducts[] = $merchantProduct;
            }
        }

        $items = $this->storeCatalogProductBatchMapper->toOutputs($merchantProducts);

        return new CustomerFavoriteProductListOutput(
            storeId: $storeId,
            items: $items,
            total: \count($items),
        );
    }
}
