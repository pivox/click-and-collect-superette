<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BillingDocumentOutput;
use App\Entity\User;
use App\Repository\BillingDocumentRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<BillingDocumentOutput>
 */
final readonly class MerchantBillingDocumentItemProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
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
        $merchant = $this->security->getUser();
        if (!$merchant instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_ACCESS_REQUIRED');
        }

        $documentId = (string) ($uriVariables['billingDocumentId'] ?? '');
        if (!Uuid::isValid($documentId)) {
            throw new NotFoundHttpException('MERCHANT_BILLING_DOCUMENT_NOT_FOUND');
        }

        $document = $this->billingDocumentRepository->findOneForMerchant(Uuid::fromString($documentId), $merchant);
        if (null === $document) {
            throw new NotFoundHttpException('MERCHANT_BILLING_DOCUMENT_NOT_FOUND');
        }

        return $this->outputFactory->fromBillingDocument($document);
    }
}
