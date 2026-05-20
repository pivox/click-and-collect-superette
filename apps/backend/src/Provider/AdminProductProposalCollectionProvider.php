<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductProposalOutput;
use App\Enum\ProductReferenceProposalStatus;
use App\Repository\ProductReferenceProposalRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<AdminProductProposalOutput>
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
     *
     * @return list<AdminProductProposalOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();

        $statusFilter = $request?->query->getString('status') ?: null;
        if (null !== $statusFilter && null === ProductReferenceProposalStatus::tryFrom($statusFilter)) {
            throw new BadRequestHttpException('ADMIN_PRODUCT_PROPOSAL_INVALID_STATUS_FILTER');
        }

        $page = max(self::DEFAULT_PAGE, (int) ($request?->query->get('page') ?? self::DEFAULT_PAGE));
        $limit = min(self::MAX_LIMIT, max(1, (int) ($request?->query->get('limit') ?? self::DEFAULT_LIMIT)));
        $offset = ($page - 1) * $limit;

        $criteria = null !== $statusFilter
            ? ['status' => ProductReferenceProposalStatus::from($statusFilter)]
            : [];

        $proposals = $this->proposalRepository->findBy($criteria, ['createdAt' => 'DESC'], $limit, $offset);

        // TODO: retourner un objet ListOutput avec total/page/limit (cf. AdminProductReferenceCollectionProvider)

        return array_map(
            static fn ($p) => AdminProductProposalItemProvider::toOutput($p),
            $proposals,
        );
    }
}
