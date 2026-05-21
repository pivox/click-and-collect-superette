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

        $productReference->setStatus(ProductReferenceStatus::Archived);
        $this->auditLogger->log(
            action: 'product_reference.archive',
            resourceType: 'product_reference',
            resourceId: $productReference->getId()->toRfc4122(),
            summary: \sprintf('Produit référentiel "%s" archivé.', $productReference->getNameFr()),
            metadata: ['name_fr' => $productReference->getNameFr()],
        );
        $this->adminProductReferenceRepository->save($productReference);

        return AdminProductReferenceItemProvider::toOutput($productReference);
    }
}
