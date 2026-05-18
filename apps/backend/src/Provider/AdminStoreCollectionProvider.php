<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminStoreListOutput;
use App\ApiResource\AdminStoreOutputFactory;
use App\Repository\AdminStoreRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<AdminStoreListOutput>
 */
final readonly class AdminStoreCollectionProvider implements ProviderInterface
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 50;

    public function __construct(
        private AdminStoreRepository $adminStoreRepository,
        private AdminStoreOutputFactory $adminStoreOutputFactory,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminStoreListOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_STORE_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_STORE_INVALID_LIMIT');
        $limit = min(self::MAX_LIMIT, $limit);
        $isActive = $this->parseOptionalBoolean($request?->query->get('is_active'));
        $offset = ($page - 1) * $limit;

        $stores = $this->adminStoreRepository->findPaginated($limit, $offset, $isActive);
        $productCounts = $this->adminStoreRepository->countProductsGrouped($stores);
        $items = array_map(
            fn ($shop) => $this->adminStoreOutputFactory->create(
                shop: $shop,
                productsCount: $productCounts[$shop->getId()->toRfc4122()] ?? 0,
            ),
            $stores,
        );

        return new AdminStoreListOutput(
            id: 'admin-stores',
            items: $items,
            page: $page,
            limit: $limit,
            total: $this->adminStoreRepository->countAll($isActive),
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

    private function parseOptionalBoolean(mixed $raw): ?bool
    {
        if (null === $raw || '' === $raw) {
            return null;
        }

        $value = filter_var($raw, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        if (null === $value) {
            throw new BadRequestHttpException('ADMIN_STORE_INVALID_IS_ACTIVE');
        }

        return $value;
    }
}
