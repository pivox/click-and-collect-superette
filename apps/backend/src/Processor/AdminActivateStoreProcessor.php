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

        if (null !== $shop->getArchivedAt()) {
            throw new ConflictHttpException('ADMIN_STORE_ARCHIVED');
        }

        $shop->setActive(true);
        $this->auditLogger->log(
            action: 'store.activate',
            resourceType: 'store',
            resourceId: $shop->getId()->toRfc4122(),
            summary: \sprintf('Supérette "%s" activée.', $shop->getName()),
            metadata: ['name' => $shop->getName()],
        );
        $this->adminStoreRepository->save($shop);

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
