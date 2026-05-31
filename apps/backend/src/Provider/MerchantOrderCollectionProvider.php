<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantOrderListOutput;
use App\ApiResource\MerchantOrderOutput;
use App\ApiResource\MerchantOrderSummaryOutput;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\PickupSlotDisplayTime;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantOrderListOutput>
 */
final readonly class MerchantOrderCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantOrderListOutput
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

        $request = $this->requestStack->getCurrentRequest();
        $status = $request?->query->get('status') ?: null;
        $page = max(1, (int) ($request?->query->get('page') ?? 1));
        $limit = min(50, max(1, (int) ($request?->query->get('limit') ?? 20)));
        $offset = ($page - 1) * $limit;

        $orders = $this->orderRepository->findByShopPaginated($shop, $status, $limit, $offset);
        $total = $this->orderRepository->countByShop($shop, $status);

        $items = array_map(
            static fn (Order $order): MerchantOrderSummaryOutput => self::toSummaryOutput($order),
            $orders,
        );

        return new MerchantOrderListOutput(
            id: $storeId,
            items: $items,
            total: $total,
            page: $page,
            limit: $limit,
        );
    }

    private static function toSummaryOutput(Order $order): MerchantOrderSummaryOutput
    {
        $slot = $order->getPickupSlot();

        return new MerchantOrderSummaryOutput(
            id: $order->getId()->toRfc4122(),
            storeId: $order->getShop()->getId()->toRfc4122(),
            orderNumber: $order->getOrderNumber(),
            orderNumberDisplay: $order->getOrderNumberDisplay(),
            status: $order->getStatus()->value,
            totalTnd: $order->getTotalTnd(),
            pickupSlot: null === $slot ? null : [
                'id' => $slot->getId()->toRfc4122(),
                'starts_at' => PickupSlotDisplayTime::toLocalAtom($slot->getStartsAt()),
                'ends_at' => PickupSlotDisplayTime::toLocalAtom($slot->getEndsAt()),
            ],
            lineCount: $order->getLines()->count(),
            createdAt: $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    public static function toOutput(Order $order): MerchantOrderOutput
    {
        $lines = array_map(
            static fn (OrderLine $l): \App\ApiResource\OrderLineOutput => new \App\ApiResource\OrderLineOutput(
                merchantProductId: $l->getMerchantProduct()->getId()->toRfc4122(),
                quantity: $l->getQuantity(),
                unitPriceTnd: $l->getUnitPriceTnd(),
                lineTotalTnd: $l->getLineTotalTnd(),
            ),
            $order->getLines()->toArray(),
        );

        $slot = $order->getPickupSlot();

        return new MerchantOrderOutput(
            id: $order->getId()->toRfc4122(),
            storeId: $order->getShop()->getId()->toRfc4122(),
            orderNumber: $order->getOrderNumber(),
            orderNumberDisplay: $order->getOrderNumberDisplay(),
            status: $order->getStatus()->value,
            totalTnd: $order->getTotalTnd(),
            pickupSlotId: $slot?->getId()->toRfc4122(),
            notes: $order->getNotes(),
            lines: $lines,
            rejectionReason: $order->getRejectionReason(),
            createdAt: $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
