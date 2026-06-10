<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminProductReferenceOutput;
use App\Dto\AdminRejectProductReferenceInput;
use App\Enum\ProductReferenceStatus;
use App\Provider\AdminProductReferenceItemProvider;
use App\Repository\AdminProductReferenceRepository;
use App\Repository\ProductImageRepository;
use App\Service\AdminAuditLogger;
use App\Service\ProductImage\ProductImageUrlBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminRejectProductReferenceInput, AdminProductReferenceOutput>
 */
final readonly class AdminRejectProductReferenceProcessor implements ProcessorInterface
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
     * @param AdminRejectProductReferenceInput $data
     * @param array<string, mixed>             $uriVariables
     * @param array<string, mixed>             $context
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

        $this->logger->debug('admin.product_reference.reject.start', ['product_reference_id' => $productReferenceId]);

        try {
            $productReference->setStatus(ProductReferenceStatus::Rejected);
            $productReference->setRejectionReason($data->rejection_reason ?? null);
            $this->auditLogger->log(
                action: 'product_reference.reject',
                resourceType: 'product_reference',
                resourceId: $productReference->getId()->toRfc4122(),
                summary: \sprintf('Produit référentiel "%s" rejeté.', $productReference->getNameFr()),
                metadata: [
                    'name_fr' => $productReference->getNameFr(),
                    'rejection_reason' => $data->rejection_reason,
                ],
            );
            $this->adminProductReferenceRepository->save($productReference);

            $this->logger->info('admin.product_reference.rejected', [
                'product_reference_id' => $productReferenceId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('admin.product_reference.reject_failed', [
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
