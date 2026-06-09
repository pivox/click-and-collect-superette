<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminProductReferenceOutput;
use App\Enum\ProductReferenceStatus;
use App\Provider\AdminProductReferenceItemProvider;
use App\Repository\AdminProductReferenceRepository;
use App\Repository\ProductImageRepository;
use App\Service\AdminAuditLogger;
use App\Service\ProductImage\ProductImageUrlBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, AdminProductReferenceOutput>
 */
final readonly class AdminApproveProductReferenceProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminProductReferenceRepository $adminProductReferenceRepository,
        private ProductImageRepository $productImageRepository,
        private ProductImageUrlBuilder $productImageUrlBuilder,
        private AdminAuditLogger $auditLogger,
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminProductReferenceOutput
    {
        $productReferenceId = (string) ($uriVariables['productReferenceId'] ?? '');
        if (!Uuid::isValid($productReferenceId)) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $productReference = $this->adminProductReferenceRepository->findOne($productReferenceId);
        if (null === $productReference) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $this->logger->debug('admin.product_reference.approve.start', ['product_reference_id' => $productReferenceId]);

        // Guard against duplicate barcodes when moving back to a non-archived status.
        // findOneByBarcode() ignores archived rows, so approving an archived reference
        // could otherwise create two active references sharing the same barcode and
        // break barcode-based matching/imports (same check as the normal update path).
        $barcode = $productReference->getBarcode();
        if (null !== $barcode) {
            $existing = $this->adminProductReferenceRepository->findOneByBarcode($barcode);
            if (null !== $existing && !$existing->getId()->equals($productReference->getId())) {
                throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_BARCODE_DUPLICATE');
            }
        }

        try {
            $productReference->setStatus(ProductReferenceStatus::Approved);
            // Clear any stale rejection reason when moving out of the rejected status,
            // otherwise an approved reference keeps exposing its previous rejection reason.
            $productReference->setRejectionReason(null);
            $this->auditLogger->log(
                action: 'product_reference.approve',
                resourceType: 'product_reference',
                resourceId: $productReference->getId()->toRfc4122(),
                summary: \sprintf('Produit référentiel "%s" approuvé.', $productReference->getNameFr()),
                metadata: ['name_fr' => $productReference->getNameFr()],
            );
            $this->adminProductReferenceRepository->save($productReference);

            $this->logger->info('admin.product_reference.approved', [
                'product_reference_id' => $productReferenceId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('admin.product_reference.approve_failed', [
                'product_reference_id' => $productReferenceId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $image = $this->productImageUrlBuilder->build(
            $this->productImageRepository->findOfficialForProductReference($productReference),
        );

        return AdminProductReferenceItemProvider::toOutput($productReference, $image);
    }
}
