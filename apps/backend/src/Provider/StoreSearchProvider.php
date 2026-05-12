<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\StoreSearchItemOutput;
use App\ApiResource\StoreSearchOutput;
use App\Entity\Shop;
use App\Repository\ShopRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<StoreSearchOutput>
 */
final readonly class StoreSearchProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StoreSearchOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $query = $request?->query->getString('query') ?: null;
        $city = $request?->query->getString('city') ?: null;

        $shops = $this->shopRepository->findActiveBySearchCriteria($query, $city);

        $items = array_map(
            static fn (Shop $shop): StoreSearchItemOutput => new StoreSearchItemOutput(
                storeId: $shop->getId()->toRfc4122(),
                name: $shop->getName(),
                slug: $shop->getSlug(),
                city: $shop->getCity(),
                country: $shop->getCountry(),
                isActive: $shop->isActive(),
            ),
            $shops,
        );

        return new StoreSearchOutput($items, \count($items));
    }
}
