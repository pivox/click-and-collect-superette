<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\StoreByQrOutput;
use App\Repository\ShopRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<StoreByQrOutput>
 */
final readonly class StoreByQrProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StoreByQrOutput
    {
        $token = (string) ($uriVariables['qrCodeToken'] ?? '');
        if ('' === $token) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->findActiveByQrCodeToken($token);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $storeId = $shop->getId()->toRfc4122();

        return new StoreByQrOutput(
            storeId: $storeId,
            name: $shop->getName(),
            slug: $shop->getSlug(),
            city: $shop->getCity(),
            country: $shop->getCountry(),
            isActive: $shop->isActive(),
            themeUrl: \sprintf('/api/stores/%s/theme', $storeId),
            catalogUrl: \sprintf('/api/stores/%s/catalog', $storeId),
            qrCodeToken: $token,
        );
    }
}
