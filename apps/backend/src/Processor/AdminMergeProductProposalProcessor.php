<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AdminMergeProductProposalInput;
use App\Entity\ProductReferenceProposal;
use App\Enum\ProductReferenceProposalStatus;
use App\Enum\ProductReferenceStatus;
use App\Repository\AdminProductReferenceRepository;
use App\Repository\ProductReferenceProposalRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
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
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
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
            $this->logger->warning('admin.product_proposal.already_processed', [
                'proposal_id' => $proposalId,
                'status' => $proposal->getStatus()->value,
            ]);
            throw new ConflictHttpException('ADMIN_PRODUCT_PROPOSAL_ALREADY_PROCESSED');
        }

        $productReference = $this->productReferenceRepository->findOne($data->productReferenceId);
        if (null === $productReference) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        if (ProductReferenceStatus::Archived === $productReference->getStatus()) {
            $this->logger->warning('admin.product_proposal.merge_archived_reference', [
                'proposal_id' => $proposalId,
                'product_reference_id' => $data->productReferenceId,
            ]);
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_IS_ARCHIVED');
        }

        try {
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

            $this->logger->info('admin.product_proposal.merged', [
                'proposal_id' => $proposalId,
                'product_reference_id' => $productReference->getId()->toRfc4122(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('admin.product_proposal.merge_failed', [
                'proposal_id' => $proposalId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
