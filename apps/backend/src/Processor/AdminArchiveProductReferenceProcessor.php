<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminProductReferenceOutput;
use App\Enum\ProductReferenceStatus;
use App\Provider\AdminProductReferenceItemProvider;
use App\Repository\AdminProductReferenceRepository;
use App\Service\AdminAuditLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, AdminProductReferenceOutput>
 */
final readonly class AdminArchiveProductReferenceProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminProductReferenceRepository $adminProductReferenceRepository,
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

        $this->logger->debug('admin.product_reference.archive.start', ['product_reference_id' => $productReferenceId]);

        try {
            $productReference->setStatus(ProductReferenceStatus::Archived);
            $this->auditLogger->log(
                action: 'product_reference.archive',
                resourceType: 'product_reference',
                resourceId: $productReference->getId()->toRfc4122(),
                summary: \sprintf('Produit référentiel "%s" archivé.', $productReference->getNameFr()),
                metadata: ['name_fr' => $productReference->getNameFr()],
            );
            $this->adminProductReferenceRepository->save($productReference);

            $this->logger->info('admin.product_reference.archived', [
                'product_reference_id' => $productReferenceId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('admin.product_reference.archive_failed', [
                'product_reference_id' => $productReferenceId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return AdminProductReferenceItemProvider::toOutput($productReference);
    }
}
