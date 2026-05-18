<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminCategoryOutput;
use App\Entity\Category;
use App\Repository\AdminCategoryRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminCategoryOutput>
 */
final readonly class AdminCategoryItemProvider implements ProviderInterface
{
    public function __construct(
        private AdminCategoryRepository $adminCategoryRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminCategoryOutput
    {
        $categoryId = (string) ($uriVariables['categoryId'] ?? '');
        if (!Uuid::isValid($categoryId)) {
            throw new NotFoundHttpException('ADMIN_CATEGORY_NOT_FOUND');
        }

        $category = $this->adminCategoryRepository->findOne($categoryId);
        if (null === $category) {
            throw new NotFoundHttpException('ADMIN_CATEGORY_NOT_FOUND');
        }

        return self::toOutput($category);
    }

    public static function toOutput(Category $category): AdminCategoryOutput
    {
        return new AdminCategoryOutput(
            id: $category->getId()->toRfc4122(),
            nameFr: $category->getNameFr(),
            nameAr: $category->getNameAr(),
            slug: $category->getSlug(),
            isActive: $category->isActive(),
            sortOrder: $category->getSortOrder(),
            parentId: $category->getParent()?->getId()->toRfc4122(),
            createdAt: $category->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $category->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
