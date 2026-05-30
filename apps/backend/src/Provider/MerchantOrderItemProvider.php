<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantOrderDetailOutput;
use App\ApiResource\MerchantOrderLineOutput;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\PickupSlotDisplayTime;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantOrderDetailOutput>
 */
final readonly class MerchantOrderItemProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantOrderDetailOutput
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

        $orderId = (string) ($uriVariables['id'] ?? $uriVariables['orderId'] ?? '');
        if (!Uuid::isValid($orderId)) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        $order = $this->orderRepository->findOneByShopAndIdWithDetail($shop, $orderId);
        if (null === $order) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        return self::toOutput($order);
    }

    public static function toOutput(Order $order): MerchantOrderDetailOutput
    {
        $slot = $order->getPickupSlot();
        $customer = $order->getCustomer();
        $canExposeCustomerContact = !\in_array(
            $order->getStatus(),
            [OrderStatus::Rejected, OrderStatus::Completed, OrderStatus::Cancelled],
            true,
        );

        $lines = array_map(
            static fn (OrderLine $line): MerchantOrderLineOutput => new MerchantOrderLineOutput(
                merchantProductId: $line->getMerchantProduct()->getId()->toRfc4122(),
                productName: $line->getMerchantProduct()->getDisplayNameFr(),
                quantity: $line->getQuantity(),
                unitPriceTnd: $line->getUnitPriceTnd(),
                lineTotalTnd: $line->getLineTotalTnd(),
                prepared: $line->isPrepared(),
            ),
            $order->getLines()->toArray(),
        );

        return new MerchantOrderDetailOutput(
            id: $order->getId()->toRfc4122(),
            storeId: $order->getShop()->getId()->toRfc4122(),
            status: $order->getStatus()->value,
            totalTnd: $order->getTotalTnd(),
            pickupSlot: null === $slot ? null : [
                'id' => $slot->getId()->toRfc4122(),
                'starts_at' => PickupSlotDisplayTime::toLocalAtom($slot->getStartsAt()),
                'ends_at' => PickupSlotDisplayTime::toLocalAtom($slot->getEndsAt()),
            ],
            notes: $order->getNotes(),
            lines: $lines,
            customerName: $canExposeCustomerContact ? $customer->getName() : null,
            customerPhone: $canExposeCustomerContact ? $customer->getPhone() : null,
            rejectionReason: $order->getRejectionReason(),
            createdAt: $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
