<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantProductPackOutput;
use App\Factory\ProductPackOutputFactory;
use App\Repository\ProductPackRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantProductPackOutput>
 */
final readonly class MerchantProductPackCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ProductPackRepository $productPackRepository,
        private EntityManagerInterface $entityManager,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private ProductPackOutputFactory $outputFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return MerchantProductPackOutput[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->entityManager->getRepository('App\Entity\Shop')->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $packs = $this->productPackRepository->findByShop($shop);

        return array_map(fn ($pack) => $this->outputFactory->toOutput($pack), $packs);
    }
}
