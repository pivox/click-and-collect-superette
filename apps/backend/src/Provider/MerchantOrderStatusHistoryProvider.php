<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\OrderStatusHistoryOutput;
use App\ApiResource\OrderStatusTransitionOutput;
use App\Repository\OrderRepository;
use App\Repository\OrderStatusLogRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<OrderStatusHistoryOutput>
 */
final readonly class MerchantOrderStatusHistoryProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private OrderStatusLogRepository $orderStatusLogRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): OrderStatusHistoryOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $orderId = (string) ($uriVariables['orderId'] ?? '');
        if (!Uuid::isValid($orderId)) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        $order = $this->orderRepository->findOneByShopAndId($shop, $orderId);
        if (null === $order) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        return new OrderStatusHistoryOutput(
            orderId: $order->getId()->toRfc4122(),
            transitions: array_map(
                static fn ($log): OrderStatusTransitionOutput => new OrderStatusTransitionOutput(
                    status: $log->getStatus()->value,
                    note: $log->getNote(),
                    at: $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ),
                $this->orderStatusLogRepository->findChronologicalForOrder($order),
            ),
        );
    }
}
