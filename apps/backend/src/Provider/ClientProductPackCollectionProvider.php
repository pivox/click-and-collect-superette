<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantProductPackOutput;
use App\Factory\ProductPackOutputFactory;
use App\Repository\ProductPackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantProductPackOutput>
 */
final readonly class ClientProductPackCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ProductPackRepository $productPackRepository,
        private EntityManagerInterface $entityManager,
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
        $shopId = (string) ($uriVariables['shopId'] ?? '');
        if (!Uuid::isValid($shopId)) {
            throw new NotFoundHttpException('SHOP_NOT_FOUND');
        }

        $shop = $this->entityManager->getRepository('App\Entity\Shop')->find($shopId);
        if (null === $shop) {
            throw new NotFoundHttpException('SHOP_NOT_FOUND');
        }

        $packs = $this->productPackRepository->findByShopAndActive($shop, true);

        return array_map(fn ($pack) => $this->outputFactory->toOutput($pack), $packs);
    }
}
