<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminStoreQrOutput;
use App\ApiResource\AdminStoreQrOutputFactory;
use App\Repository\AdminStoreRepository;
use App\Service\AdminAuditLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, AdminStoreQrOutput>
 */
final readonly class AdminRegenerateStoreQrProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminStoreRepository $adminStoreRepository,
        private AdminStoreQrOutputFactory $adminStoreQrOutputFactory,
        private AdminAuditLogger $auditLogger,
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminStoreQrOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('ADMIN_STORE_NOT_FOUND');
        }

        $shop = $this->adminStoreRepository->findOne($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('ADMIN_STORE_NOT_FOUND');
        }

        $this->logger->debug('admin.store_qr.regenerate.start', ['store_id' => $storeId]);

        try {
            $shop->setQrCodeToken(Uuid::v4()->toRfc4122());
            $this->auditLogger->log(
                action: 'store.qr_regenerate',
                resourceType: 'store',
                resourceId: $shop->getId()->toRfc4122(),
                summary: \sprintf('QR code de la supérette "%s" régénéré.', $shop->getName()),
                metadata: ['name' => $shop->getName()],
            );
            $this->adminStoreRepository->save($shop);

            $this->logger->info('admin.store_qr.regenerated', ['store_id' => $storeId]);
        } catch (\Throwable $e) {
            $this->logger->error('admin.store_qr.regenerate_failed', [
                'store_id' => $storeId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $this->adminStoreQrOutputFactory->create($shop);
    }
}
