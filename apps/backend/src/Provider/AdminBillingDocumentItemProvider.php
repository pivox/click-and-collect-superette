<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BillingDocumentOutput;
use App\Repository\BillingDocumentRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<BillingDocumentOutput>
 */
final readonly class AdminBillingDocumentItemProvider implements ProviderInterface
{
    public function __construct(
        private BillingDocumentRepository $billingDocumentRepository,
        private BillingDocumentOutputFactory $outputFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BillingDocumentOutput
    {
        $documentId = (string) ($uriVariables['billingDocumentId'] ?? '');
        if (!Uuid::isValid($documentId)) {
            throw new NotFoundHttpException('ADMIN_BILLING_DOCUMENT_NOT_FOUND');
        }

        $document = $this->billingDocumentRepository->find($documentId);
        if (null === $document) {
            throw new NotFoundHttpException('ADMIN_BILLING_DOCUMENT_NOT_FOUND');
        }

        return $this->outputFactory->fromBillingDocument($document);
    }
}
