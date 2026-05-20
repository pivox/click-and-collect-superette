<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminBrandListOutput;
use App\Repository\AdminBrandRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<AdminBrandListOutput>
 */
final readonly class AdminBrandCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private AdminBrandRepository $adminBrandRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminBrandListOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_BRAND_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_BRAND_INVALID_LIMIT');
        $limit = min(self::MAX_LIMIT, $limit);
        $offset = ($page - 1) * $limit;

        $brands = $this->adminBrandRepository->findPaginated($limit, $offset);
        $items = array_map(
            static fn ($brand) => AdminBrandItemProvider::toOutput($brand),
            $brands,
        );

        return new AdminBrandListOutput(
            id: 'admin-brands',
            items: $items,
            page: $page,
            limit: $limit,
            total: $this->adminBrandRepository->countAll(),
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
