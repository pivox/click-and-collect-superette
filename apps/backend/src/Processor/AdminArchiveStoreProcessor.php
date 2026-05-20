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
use App\Service\OrderStatusLogRecorder;
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
            throw new ConflictHttpException('ADMIN_STORE_ALREADY_ARCHIVED');
        }

        $reason = $data instanceof AdminArchiveStoreInput ? $data->reason : null;

        $shop->archive($reason);

        $activeOrders = $this->orderRepository->findActiveByShop($shop);
        foreach ($activeOrders as $order) {
            $order->forceCancel();
            $slot = $order->getPickupSlot();
            if (null !== $slot) {
                $this->entityManager->getConnection()->executeStatement(
                    'UPDATE pickup_slots SET booked_count = CASE WHEN booked_count > 0 THEN booked_count - 1 ELSE 0 END WHERE id = :id',
                    ['id' => $slot->getId()->toBinary()],
                );
            }
            $this->orderStatusLogRecorder->record($order, OrderStatus::Cancelled, 'ADMIN_STORE_ARCHIVED');
        }

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
