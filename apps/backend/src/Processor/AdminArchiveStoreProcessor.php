<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminStoreOutput;
use App\ApiResource\AdminStoreOutputFactory;
use App\Dto\AdminArchiveStoreInput;
use App\Entity\Shop;
use App\Enum\OrderStatus;
use App\Repository\AdminStoreRepository;
use App\Repository\OrderRepository;
use App\Service\AdminAuditLogger;
use App\Service\OrderStatusLogRecorder;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminArchiveStoreInput, AdminStoreOutput>
 */
final readonly class AdminArchiveStoreProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminStoreRepository $adminStoreRepository,
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private OrderStatusLogRecorder $orderStatusLogRecorder,
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
        $reason = $data->reason;

        $this->entityManager->wrapInTransaction(function () use ($shop, $reason): void {
            $conn = $this->entityManager->getConnection();

            // Serialize concurrent archive requests. FOR UPDATE is PostgreSQL-only;
            // SQLite uses implicit table-level locking and does not support the syntax.
            if (!$conn->getDatabasePlatform() instanceof SQLitePlatform) {
                $conn->executeQuery(
                    'SELECT id FROM shops WHERE id = :id FOR UPDATE',
                    ['id' => $shop->getId()->toRfc4122()],
                );
                $this->entityManager->refresh($shop);
            }

            if (null !== $shop->getArchivedAt()) {
                throw new ConflictHttpException('ADMIN_STORE_ALREADY_ARCHIVED');
            }

            $shop->archive($reason);

            foreach ($this->orderRepository->findActiveByShop($shop) as $order) {
                $order->forceCancel();
                $slot = $order->getPickupSlot();
                if (null !== $slot) {
                    // Pass the Uuid object with explicit 'uuid' type so DBAL converts it
                    // to RFC 4122 on PostgreSQL and to binary on SQLite.
                    $conn->executeStatement(
                        'UPDATE pickup_slots SET booked_count = CASE WHEN booked_count > 0 THEN booked_count - 1 ELSE 0 END WHERE id = :id',
                        ['id' => $slot->getId()],
                        ['id' => 'uuid'],
                    );
                }
                $this->orderStatusLogRecorder->record($order, OrderStatus::Cancelled, 'ADMIN_STORE_ARCHIVED');
            }

            $this->auditLogger->log(
                action: 'store.archive',
                resourceType: 'store',
                resourceId: $shop->getId()->toRfc4122(),
                metadata: ['name' => $shop->getName(), 'reason' => $reason],
            );
            $this->adminStoreRepository->save($shop);
        });

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
