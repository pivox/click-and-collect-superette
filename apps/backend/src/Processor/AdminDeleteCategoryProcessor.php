<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\AdminCategoryRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Logical delete if the category is linked to product references, physical delete otherwise.
 *
 * @implements ProcessorInterface<null, null>
 */
final readonly class AdminDeleteCategoryProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminCategoryRepository $adminCategoryRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $categoryId = (string) ($uriVariables['categoryId'] ?? '');
        if (!Uuid::isValid($categoryId)) {
            throw new NotFoundHttpException('ADMIN_CATEGORY_NOT_FOUND');
        }

        $category = $this->adminCategoryRepository->findOne($categoryId);
        if (null === $category) {
            throw new NotFoundHttpException('ADMIN_CATEGORY_NOT_FOUND');
        }

        if ($this->adminCategoryRepository->countLinkedProductReferences($category) > 0) {
            $category->setActive(false);
            $this->adminCategoryRepository->save($category);
        } else {
            $this->adminCategoryRepository->remove($category);
        }

        return null;
    }
}
