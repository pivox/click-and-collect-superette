<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductReferenceListOutput;
use App\Repository\AdminProductReferenceRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<AdminProductReferenceListOutput>
 */
final readonly class AdminProductReferenceCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private AdminProductReferenceRepository $adminProductReferenceRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminProductReferenceListOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_PRODUCT_REFERENCE_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_PRODUCT_REFERENCE_INVALID_LIMIT');
        $limit = min(self::MAX_LIMIT, $limit);
        $offset = ($page - 1) * $limit;

        $q = $request?->query->getString('q') ?: null;
        $categoryId = $request?->query->getString('category') ?: null;
        $brandId = $request?->query->getString('brand') ?: null;
        $status = $request?->query->getString('status') ?: null;

        $productReferences = $this->adminProductReferenceRepository->findPaginated($limit, $offset, $q, $categoryId, $brandId, $status);
        $items = array_map(
            static fn ($ref) => AdminProductReferenceItemProvider::toOutput($ref),
            $productReferences,
        );

        return new AdminProductReferenceListOutput(
            id: 'admin-product-references',
            items: $items,
            page: $page,
            limit: $limit,
            total: $this->adminProductReferenceRepository->countFiltered($q, $categoryId, $brandId, $status),
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
