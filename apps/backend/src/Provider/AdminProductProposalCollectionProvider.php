<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductProposalListOutput;
use App\Enum\ProductReferenceProposalStatus;
use App\Repository\ProductReferenceProposalRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<AdminProductProposalListOutput>
 */
final readonly class AdminProductProposalCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private ProductReferenceProposalRepository $proposalRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminProductProposalListOutput
    {
        $request = $this->requestStack->getCurrentRequest();

        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_PRODUCT_PROPOSAL_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_PRODUCT_PROPOSAL_INVALID_LIMIT');
        $limit = \min(self::MAX_LIMIT, $limit);
        $offset = ($page - 1) * $limit;

        $statusFilter = $request?->query->getString('status') ?: null;
        if (null !== $statusFilter && null === ProductReferenceProposalStatus::tryFrom($statusFilter)) {
            throw new BadRequestHttpException('ADMIN_PRODUCT_PROPOSAL_INVALID_STATUS_FILTER');
        }

        $status = null !== $statusFilter ? ProductReferenceProposalStatus::from($statusFilter) : null;

        $proposals = $this->proposalRepository->findPaginated($limit, $offset, $status);
        $items = \array_map(
            static fn ($p) => AdminProductProposalItemProvider::toOutput($p),
            $proposals,
        );

        return new AdminProductProposalListOutput(
            id: 'admin-product-proposals',
            items: $items,
            page: $page,
            limit: $limit,
            total: $this->proposalRepository->countFiltered($status),
        );
    }

    private function parsePositiveInt(mixed $raw, int $default, string $errorCode): int
    {
        if (null === $raw || '' === $raw) {
            return $default;
        }

        if (false === \filter_var($raw, \FILTER_VALIDATE_INT)) {
            throw new BadRequestHttpException($errorCode);
        }

        $value = (int) $raw;
        if ($value < 1) {
            throw new BadRequestHttpException($errorCode);
        }

        return $value;
    }
}
