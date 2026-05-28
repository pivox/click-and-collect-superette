<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AdminApproveCanonicalData;
use App\Dto\AdminApproveProductProposalInput;
use App\Entity\ProductReference;
use App\Entity\ProductReferenceProposal;
use App\Enum\ProductReferenceProposalStatus;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Repository\AdminBrandRepository;
use App\Repository\AdminCategoryRepository;
use App\Repository\AdminProductReferenceRepository;
use App\Repository\ProductReferenceProposalRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminApproveProductProposalInput|null, void>
 */
final readonly class AdminApproveProductProposalProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductReferenceProposalRepository $proposalRepository,
        private AdminProductReferenceRepository $productReferenceRepository,
        private AdminBrandRepository $brandRepository,
        private AdminCategoryRepository $categoryRepository,
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

        $input = $data instanceof AdminApproveProductProposalInput ? $data : new AdminApproveProductProposalInput();

        if (null !== $input->productReferenceId) {
            $productReference = $this->linkToExisting($proposal, $input->productReferenceId);
        } else {
            $productReference = $this->createFromData($proposal, $input->canonicalData);
        }

        $this->auditLogger->log(
            action: 'product_proposal.approve',
            resourceType: 'product_proposal',
            resourceId: $proposalId,
            summary: \sprintf('Proposition produit "%s" validée.', $proposal->getNameFr()),
            metadata: ['product_reference_id' => $productReference->getId()->toRfc4122()],
        );

        $this->entityManager->flush();
    }

    private function linkToExisting(ProductReferenceProposal $proposal, string $productReferenceId): ProductReference
    {
        $productReference = $this->productReferenceRepository->findOne($productReferenceId);
        if (null === $productReference) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $proposal->setStatus(ProductReferenceProposalStatus::Approved);
        $proposal->setCreatedProductReference($productReference);

        return $productReference;
    }

    private function createFromData(ProductReferenceProposal $proposal, ?AdminApproveCanonicalData $canonical): ProductReference
    {
        if (null !== $canonical) {
            $productReference = $this->buildFromCanonical($canonical);
        } else {
            $productReference = $this->buildFromProposal($proposal);
        }

        $this->entityManager->persist($productReference);

        $proposal->setStatus(ProductReferenceProposalStatus::Approved);
        $proposal->setCreatedProductReference($productReference);

        return $productReference;
    }

    private function buildFromCanonical(AdminApproveCanonicalData $canonical): ProductReference
    {
        if (null === $canonical->nameFr || '' === $canonical->nameFr) {
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_PROPOSAL_CANONICAL_NAME_FR_REQUIRED');
        }
        if (null === $canonical->brandId) {
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_PROPOSAL_CANONICAL_BRAND_REQUIRED');
        }
        if (null === $canonical->categoryId) {
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_PROPOSAL_CANONICAL_CATEGORY_REQUIRED');
        }

        $brand = $this->brandRepository->findOne($canonical->brandId);
        if (null === $brand) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_BRAND_NOT_FOUND');
        }

        $category = $this->categoryRepository->findOne($canonical->categoryId);
        if (null === $category) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_CATEGORY_NOT_FOUND');
        }

        $unit = null !== $canonical->unit ? ProductUnit::from($canonical->unit) : ProductUnit::Piece;

        return (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($canonical->nameFr)
            ->setNameAr($canonical->nameAr)
            ->setVariantFr($canonical->variantFr)
            ->setVariantAr($canonical->variantAr)
            ->setUnit($unit)
            ->setBarcode($canonical->barcode)
            ->setVolume($canonical->volume)
            ->setStatus(ProductReferenceStatus::Approved);
    }

    private function buildFromProposal(ProductReferenceProposal $proposal): ProductReference
    {
        $brand = $proposal->getBrand();
        if (null === $brand) {
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_PROPOSAL_BRAND_REQUIRED_FOR_APPROVAL');
        }

        $category = $proposal->getCategory();
        if (null === $category) {
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_PROPOSAL_CATEGORY_REQUIRED_FOR_APPROVAL');
        }

        return (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($proposal->getNameFr())
            ->setNameAr($proposal->getNameAr())
            ->setVariantFr($proposal->getVariantFr())
            ->setVolume($proposal->getVolume())
            ->setUnit($proposal->getUnit())
            ->setBarcode($proposal->getBarcode())
            ->setStatus(ProductReferenceStatus::Approved);
    }
}
