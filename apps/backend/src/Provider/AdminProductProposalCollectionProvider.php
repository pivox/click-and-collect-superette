<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductProposalOutput;
use App\Entity\ProductReferenceProposal;
use App\Enum\ProductReferenceProposalStatus;
use App\Repository\ProductReferenceProposalRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<AdminProductProposalOutput>
 */
final readonly class AdminProductProposalCollectionProvider implements ProviderInterface
{
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

        $criteria = [];
        if (null !== $statusFilter) {
            $status = ProductReferenceProposalStatus::tryFrom($statusFilter);
            if (null !== $status) {
                $criteria['status'] = $status;
            }
        }

        $proposals = $this->proposalRepository->findBy($criteria, ['createdAt' => 'DESC']);

        return array_map(
            static fn (ProductReferenceProposal $p): AdminProductProposalOutput => new AdminProductProposalOutput(
                $p->getId()->toRfc4122(),
                $p->getNameFr(),
                $p->getNameAr(),
                $p->getBrand()?->getCanonicalName() ?? $p->getBrandName(),
                $p->getCategory()->getNameFr(),
                $p->getStatus()->value,
                $p->getRejectionReason(),
                $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
                $p->getProposedBy()->getEmail(),
            ),
            $proposals,
        );
    }
}
