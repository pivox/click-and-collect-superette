<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\StorePublicOutput;
use App\Repository\ShopRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<StorePublicOutput>
 */
final readonly class StorePublicProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StorePublicOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop || !$shop->isActive()) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        return new StorePublicOutput(
            storeId: $shop->getId()->toRfc4122(),
            name: $shop->getName(),
            slug: $shop->getSlug(),
            city: $shop->getCity(),
            country: $shop->getCountry(),
            isActive: $shop->isActive(),
            logoUrl: $shop->getLogoUrl(),
            coverUrl: $shop->getCoverUrl(),
        );
    }
}
