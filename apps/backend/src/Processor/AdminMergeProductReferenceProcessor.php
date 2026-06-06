<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminProductReferenceMergeOutput;
use App\Dto\AdminMergeProductReferenceInput;
use App\Entity\User;
use App\Provider\AdminProductReferenceItemProvider;
use App\Repository\AdminProductReferenceRepository;
use App\Service\AdminAuditLogger;
use App\Service\ProductReferenceDeduplicationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminMergeProductReferenceInput, AdminProductReferenceMergeOutput>
 */
final readonly class AdminMergeProductReferenceProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminProductReferenceRepository $productReferenceRepository,
        private ProductReferenceDeduplicationService $deduplicationService,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private AdminAuditLogger $auditLogger,
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminProductReferenceMergeOutput
    {
        if (!$data instanceof AdminMergeProductReferenceInput) {
            throw new \InvalidArgumentException('AdminMergeProductReferenceInput expected.');
        }

        $absorbedId = (string) ($uriVariables['productReferenceId'] ?? '');
        if (!Uuid::isValid($absorbedId) || !Uuid::isValid($data->keptProductReferenceId)) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $absorbed = $this->productReferenceRepository->findOne($absorbedId);
        $kept = $this->productReferenceRepository->findOne($data->keptProductReferenceId);
        if (null === $absorbed || null === $kept) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $admin = $this->security->getUser();
        if (!$admin instanceof User) {
            throw new \LogicException('Admin merge requires an authenticated User.');
        }

        $this->logger->debug('admin.product_reference.merge.start', [
            'absorbed_product_reference_id' => $absorbedId,
            'kept_product_reference_id' => $data->keptProductReferenceId,
        ]);

        $result = $this->deduplicationService->merge($absorbed, $kept, $admin);
        $history = $result['history'];

        $this->auditLogger->log(
            action: 'product_reference.merge',
            resourceType: 'product_reference',
            resourceId: $kept->getId()->toRfc4122(),
            summary: \sprintf('Produit référentiel "%s" fusionné dans "%s".', $absorbed->getNameFr(), $kept->getNameFr()),
            metadata: [
                'kept_product_reference_id' => $kept->getId()->toRfc4122(),
                'absorbed_product_reference_id' => $absorbed->getId()->toRfc4122(),
                'history_id' => $history->getId()->toRfc4122(),
                'reason' => $history->getReason(),
                'moved_offer_count' => $result['movedOfferCount'],
                'removed_conflicting_offer_count' => $result['removedConflictingOfferCount'],
            ],
        );

        $this->entityManager->flush();

        $this->logger->info('admin.product_reference.merged', [
            'absorbed_product_reference_id' => $absorbedId,
            'kept_product_reference_id' => $data->keptProductReferenceId,
            'history_id' => $history->getId()->toRfc4122(),
        ]);

        return new AdminProductReferenceMergeOutput(
            id: $history->getId()->toRfc4122(),
            kept: AdminProductReferenceItemProvider::toOutput($kept),
            absorbed: AdminProductReferenceItemProvider::toOutput($absorbed),
            movedOfferCount: $result['movedOfferCount'],
            removedConflictingOfferCount: $result['removedConflictingOfferCount'],
            historyId: $history->getId()->toRfc4122(),
        );
    }
}
