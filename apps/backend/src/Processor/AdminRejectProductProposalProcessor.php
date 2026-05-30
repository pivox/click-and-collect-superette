<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AdminRejectProductProposalInput;
use App\Entity\ProductReferenceProposal;
use App\Enum\ProductReferenceProposalStatus;
use App\Repository\ProductReferenceProposalRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminRejectProductProposalInput, void>
 */
final readonly class AdminRejectProductProposalProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductReferenceProposalRepository $proposalRepository,
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
        if (!$data instanceof AdminRejectProductProposalInput) {
            throw new \InvalidArgumentException('AdminRejectProductProposalInput expected.');
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

        try {
            $proposal->setStatus(ProductReferenceProposalStatus::Rejected);
            $proposal->setRejectionReason($data->reason);

            $this->auditLogger->log(
                action: 'product_proposal.reject',
                resourceType: 'product_proposal',
                resourceId: $proposalId,
                summary: \sprintf('Proposition produit "%s" rejetée.', $proposal->getNameFr()),
                metadata: ['rejection_reason' => $data->reason],
            );

            $this->entityManager->flush();

            $this->logger->info('admin.product_proposal.rejected', [
                'proposal_id' => $proposalId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('admin.product_proposal.reject_failed', [
                'proposal_id' => $proposalId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
