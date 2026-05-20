<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductProposalOutput;
use App\Entity\ProductReferenceProposal;
use App\Repository\ProductReferenceProposalRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminProductProposalOutput>
 */
final readonly class AdminProductProposalItemProvider implements ProviderInterface
{
    public function __construct(
        private ProductReferenceProposalRepository $proposalRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminProductProposalOutput
    {
        $proposalId = (string) ($uriVariables['proposalId'] ?? '');
        if (!Uuid::isValid($proposalId)) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_PROPOSAL_NOT_FOUND');
        }

        $proposal = $this->proposalRepository->find($proposalId);
        if (!$proposal instanceof ProductReferenceProposal) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_PROPOSAL_NOT_FOUND');
        }

        return self::toOutput($proposal);
    }

    public static function toOutput(ProductReferenceProposal $proposal): AdminProductProposalOutput
    {
        return new AdminProductProposalOutput(
            id: $proposal->getId()->toRfc4122(),
            nameFr: $proposal->getNameFr(),
            nameAr: $proposal->getNameAr(),
            brandName: $proposal->getBrand()?->getCanonicalName() ?? $proposal->getBrandName(),
            category: $proposal->getCategory()->getNameFr(),
            status: $proposal->getStatus()->value,
            rejectionReason: $proposal->getRejectionReason(),
            createdAt: $proposal->getCreatedAt()->format(\DateTimeInterface::ATOM),
            proposedBy: $proposal->getProposedBy()->getEmail(),
            createdProductReferenceId: $proposal->getCreatedProductReference()?->getId()->toRfc4122(),
        );
    }
}
