<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MergeProposalInput;
use App\Entity\ProductReference;
use App\Entity\ProductReferenceProposal;
use App\Enum\ProductReferenceProposalStatus;
use App\Repository\ProductReferenceProposalRepository;
use App\Repository\ProductReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MergeProposalInput, void>
 */
final readonly class MergeProductProposalProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductReferenceProposalRepository $proposalRepository,
        private ProductReferenceRepository $productReferenceRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof MergeProposalInput) {
            throw new \InvalidArgumentException('MergeProposalInput expected.');
        }

        $proposalId = (string) ($uriVariables['proposalId'] ?? '');
        if (!Uuid::isValid($proposalId)) {
            throw new NotFoundHttpException('PROPOSAL_NOT_FOUND');
        }

        $proposal = $this->proposalRepository->find($proposalId);
        if (!$proposal instanceof ProductReferenceProposal) {
            throw new NotFoundHttpException('PROPOSAL_NOT_FOUND');
        }

        if (!Uuid::isValid($data->productReferenceId)) {
            throw new NotFoundHttpException('PRODUCT_REFERENCE_NOT_FOUND');
        }

        $productReference = $this->productReferenceRepository->find($data->productReferenceId);
        if (!$productReference instanceof ProductReference) {
            throw new NotFoundHttpException('PRODUCT_REFERENCE_NOT_FOUND');
        }

        $proposal->setStatus(ProductReferenceProposalStatus::Merged);
        $proposal->setCreatedProductReference($productReference);

        $this->entityManager->flush();
    }
}
