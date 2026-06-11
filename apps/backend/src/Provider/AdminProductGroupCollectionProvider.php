<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductGroupListOutput;
use App\Enum\ProductGroupStatus;
use App\Repository\AdminProductGroupRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<AdminProductGroupListOutput>
 */
final readonly class AdminProductGroupCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private AdminProductGroupRepository $adminProductGroupRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminProductGroupListOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_PRODUCT_GROUP_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_PRODUCT_GROUP_INVALID_LIMIT');
        $limit = min(self::MAX_LIMIT, $limit);
        $offset = ($page - 1) * $limit;

        $q = $request?->query->getString('q') ?: null;
        $status = $request?->query->getString('status') ?: null;
        $marketCountry = $request?->query->getString('market_country') ?: null;

        if (null !== $status && null === ProductGroupStatus::tryFrom($status)) {
            throw new BadRequestHttpException('ADMIN_PRODUCT_GROUP_INVALID_STATUS_FILTER');
        }

        $groups = $this->adminProductGroupRepository->findPaginated($limit, $offset, $q, $status, $marketCountry);
        $items = array_map(
            static fn ($group) => AdminProductGroupItemProvider::toOutput($group, includeItems: false),
            $groups,
        );

        return new AdminProductGroupListOutput(
            id: 'admin-product-groups',
            items: $items,
            page: $page,
            limit: $limit,
            total: $this->adminProductGroupRepository->countFiltered($q, $status, $marketCountry),
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
