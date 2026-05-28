<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AdminMergeProductProposalInput;
use App\Entity\ProductReferenceProposal;
use App\Enum\ProductReferenceProposalStatus;
use App\Repository\AdminProductReferenceRepository;
use App\Repository\ProductReferenceProposalRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminMergeProductProposalInput, void>
 */
final readonly class AdminMergeProductProposalProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductReferenceProposalRepository $proposalRepository,
        private AdminProductReferenceRepository $productReferenceRepository,
        private EntityManagerInterface $entityManager,
        private AdminAuditLogger $auditLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof AdminMergeProductProposalInput) {
            throw new \InvalidArgumentException('AdminMergeProductProposalInput expected.');
        }

        $proposalId = (string) ($uriVariables['proposalId'] ?? '');
        if (!Uuid::isValid($proposalId)) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_PROPOSAL_NOT_FOUND');
        }

        $proposal = $this->proposalRepository->find($proposalId);
        if (!$proposal instanceof ProductReferenceProposal) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_PROPOSAL_NOT_FOUND');
        }

        if (ProductReferenceProposalStatus::Pending !== $proposal->getStatus()) {
            throw new ConflictHttpException('ADMIN_PRODUCT_PROPOSAL_ALREADY_PROCESSED');
        }

        $productReference = $this->productReferenceRepository->findOne($data->productReferenceId);
        if (null === $productReference) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $proposal->setStatus(ProductReferenceProposalStatus::Merged);
        $proposal->setCreatedProductReference($productReference);

        $this->auditLogger->log(
            action: 'product_proposal.merge',
            resourceType: 'product_proposal',
            resourceId: $proposalId,
            summary: \sprintf('Proposition produit "%s" fusionnée avec référence existante.', $proposal->getNameFr()),
            metadata: ['product_reference_id' => $productReference->getId()->toRfc4122()],
        );

        $this->entityManager->flush();
    }
}
