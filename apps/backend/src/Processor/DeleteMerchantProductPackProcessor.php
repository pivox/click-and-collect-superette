<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class DeleteMerchantProductPackProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
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

        $packId = (string) ($uriVariables['packId'] ?? '');
        if (!Uuid::isValid($packId)) {
            throw new NotFoundHttpException('PRODUCT_PACK_NOT_FOUND');
        }

        $pack = $this->entityManager->getRepository('App\Entity\ProductPack')->find($packId);
        if (null === $pack || !$pack->getShop()->getId()->equals($shop->getId())) {
            throw new NotFoundHttpException('PRODUCT_PACK_NOT_FOUND');
        }

        $this->entityManager->remove($pack);
        $this->entityManager->flush();
    }
}
