<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\RejectProposalInput;
use App\Entity\ProductReferenceProposal;
use App\Enum\ProductReferenceProposalStatus;
use App\Repository\ProductReferenceProposalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<RejectProposalInput, void>
 */
final readonly class RejectProductProposalProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductReferenceProposalRepository $proposalRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof RejectProposalInput) {
            throw new \InvalidArgumentException('RejectProposalInput expected.');
        }

        $proposalId = (string) ($uriVariables['proposalId'] ?? '');
        if (!Uuid::isValid($proposalId)) {
            throw new NotFoundHttpException('PROPOSAL_NOT_FOUND');
        }

        $proposal = $this->proposalRepository->find($proposalId);
        if (!$proposal instanceof ProductReferenceProposal) {
            throw new NotFoundHttpException('PROPOSAL_NOT_FOUND');
        }

        $proposal->setStatus(ProductReferenceProposalStatus::Rejected);
        $proposal->setRejectionReason($data->reason);

        $this->entityManager->flush();
    }
}
