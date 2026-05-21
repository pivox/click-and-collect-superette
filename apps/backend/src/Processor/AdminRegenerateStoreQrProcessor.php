<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminStoreQrOutput;
use App\ApiResource\AdminStoreQrOutputFactory;
use App\Repository\AdminStoreRepository;
use App\Service\AdminAuditLogger;
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

        $shop->setQrCodeToken(Uuid::v4()->toRfc4122());
        $this->auditLogger->log(
            action: 'store.qr_regenerate',
            resourceType: 'store',
            resourceId: $shop->getId()->toRfc4122(),
            metadata: ['name' => $shop->getName()],
        );
        $this->adminStoreRepository->save($shop);

        return $this->adminStoreQrOutputFactory->create($shop);
    }
}
