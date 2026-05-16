<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantExceptionalClosureCollectionOutput;
use App\ApiResource\MerchantExceptionalClosureOutput;
use App\Entity\ExceptionalClosure;
use App\Repository\ExceptionalClosureRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantExceptionalClosureCollectionOutput>
 */
final readonly class MerchantExceptionalClosureCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private ExceptionalClosureRepository $exceptionalClosureRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantExceptionalClosureCollectionOutput
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

        $items = array_map(
            $this->toOutput(...),
            $this->exceptionalClosureRepository->findActiveForShop($shop),
        );

        return new MerchantExceptionalClosureCollectionOutput(
            total: \count($items),
            items: $items,
        );
    }

    private function toOutput(ExceptionalClosure $closure): MerchantExceptionalClosureOutput
    {
        return new MerchantExceptionalClosureOutput(
            id: $closure->getId()->toRfc4122(),
            startsAt: $closure->getStartsAt()->format(\DateTimeInterface::ATOM),
            endsAt: $closure->getEndsAt()->format(\DateTimeInterface::ATOM),
            reason: $closure->getReason(),
            isActive: $closure->isActive(),
        );
    }
}
