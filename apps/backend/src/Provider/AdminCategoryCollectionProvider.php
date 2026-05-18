<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminCategoryListOutput;
use App\Repository\AdminCategoryRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<AdminCategoryListOutput>
 */
final readonly class AdminCategoryCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private AdminCategoryRepository $adminCategoryRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminCategoryListOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_CATEGORY_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_CATEGORY_INVALID_LIMIT');
        $limit = min(self::MAX_LIMIT, $limit);
        $offset = ($page - 1) * $limit;

        $categories = $this->adminCategoryRepository->findPaginated($limit, $offset);
        $items = array_map(
            static fn ($category) => AdminCategoryItemProvider::toOutput($category),
            $categories,
        );

        return new AdminCategoryListOutput(
            id: 'admin-categories',
            items: $items,
            page: $page,
            limit: $limit,
            total: $this->adminCategoryRepository->countAll(),
        );
    }

    private function parsePositiveInt(mixed $raw, int $default, string $errorCode): int
    {
        if (null === $raw || '' === $raw) {
            return $default;
        }

        if (false === filter_var($raw, \FILTER_VALIDATE_INT)) {
            throw new BadRequestHttpException($errorCode);
        }

        $value = (int) $raw;
        if ($value < 1) {
            throw new BadRequestHttpException($errorCode);
        }

        return $value;
    }
}
