<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\ExceptionalClosureRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<object, void>
 */
final readonly class DeleteMerchantExceptionalClosureProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private ExceptionalClosureRepository $exceptionalClosureRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        $closureId = (string) ($uriVariables['closureId'] ?? '');
        if (!Uuid::isValid($storeId) || !Uuid::isValid($closureId)) {
            throw new NotFoundHttpException('EXCEPTIONAL_CLOSURE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $closure = $this->exceptionalClosureRepository->findActiveOneForShop($shop, $closureId);
        if (null === $closure) {
            throw new NotFoundHttpException('EXCEPTIONAL_CLOSURE_NOT_FOUND');
        }

        $closure->setActive(false);
        $this->entityManager->flush();
    }
}
