<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminStoreOutput;
use App\ApiResource\AdminStoreOutputFactory;
use App\Entity\Shop;
use App\Repository\AdminStoreRepository;
use App\Service\AdminAuditLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, AdminStoreOutput>
 */
final readonly class AdminActivateStoreProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminStoreRepository $adminStoreRepository,
        private AdminStoreOutputFactory $adminStoreOutputFactory,
        private AdminAuditLogger $auditLogger,
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminStoreOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        $shop = $this->resolveShop($storeId);

        $this->logger->debug('admin.store_activate.start', ['store_id' => $storeId]);

        if (null !== $shop->getArchivedAt()) {
            $this->logger->warning('admin.store_activate.rejected', [
                'store_id' => $storeId,
                'reason' => 'ADMIN_STORE_ARCHIVED',
            ]);
            throw new ConflictHttpException('ADMIN_STORE_ARCHIVED');
        }

        try {
            $shop->setActive(true);
            $this->auditLogger->log(
                action: 'store.activate',
                resourceType: 'store',
                resourceId: $shop->getId()->toRfc4122(),
                summary: \sprintf('Supérette "%s" activée.', $shop->getName()),
                metadata: ['name' => $shop->getName()],
            );
            $this->adminStoreRepository->save($shop);
            $this->logger->info('store.activated', ['store_id' => $storeId]);
        } catch (\Throwable $e) {
            $this->logger->error('admin.store_activate.failed', [
                'store_id' => $storeId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $this->adminStoreOutputFactory->create(
            shop: $shop,
            productsCount: $this->adminStoreRepository->countProducts($shop),
            exceptionalClosuresCount: $this->adminStoreRepository->countActiveExceptionalClosures($shop),
            pickupRulesCount: $this->adminStoreRepository->countActivePickupRules($shop),
        );
    }

    private function resolveShop(string $storeId): Shop
    {
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('ADMIN_STORE_NOT_FOUND');
        }

        $shop = $this->adminStoreRepository->findOne($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('ADMIN_STORE_NOT_FOUND');
        }

        return $shop;
    }
}
