<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BrandOutput;
use App\Entity\Brand;
use App\Repository\BrandRepository;

/**
 * @implements ProviderInterface<BrandOutput>
 */
final readonly class BrandCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BrandRepository $brandRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<BrandOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(
            static fn (Brand $b) => new BrandOutput($b->getId()->toRfc4122(), $b->getCanonicalName(), $b->getSlug()),
            $this->brandRepository->findActive(),
        );
    }
}
