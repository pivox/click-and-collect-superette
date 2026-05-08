<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CategoryOutput;
use App\Entity\Category;
use App\Repository\CategoryRepository;

/**
 * @implements ProviderInterface<CategoryOutput>
 */
final readonly class CategoryCollectionProvider implements ProviderInterface
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<CategoryOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(
            static fn (Category $c) => new CategoryOutput(
                $c->getId()->toRfc4122(),
                $c->getNameFr(),
                $c->getNameAr(),
                $c->getSlug(),
                $c->getParent()?->getId()->toRfc4122(),
                $c->getSortOrder(),
            ),
            $this->categoryRepository->findActive(),
        );
    }
}
