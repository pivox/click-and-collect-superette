<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ShopOpeningHoursOutput;
use App\Repository\ShopRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<ShopOpeningHoursOutput>
 */
final readonly class ShopOpeningHoursProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ShopOpeningHoursOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        if (!$shop->isActive()) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        return new ShopOpeningHoursOutput(
            storeId: $shop->getId()->toRfc4122(),
            openingHours: $shop->getOpeningHours(),
        );
    }
}
