<?php

declare(strict_types=1);

namespace App\Factory;

use App\ApiResource\OrderLineOutput;
use App\ApiResource\OrderOutput;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Enum\OrderStatus;
use App\Service\PickupSlotDisplayTime;

final readonly class OrderOutputFactory
{
    public function toOutput(Order $order): OrderOutput
    {
        $lines = array_map(
            self::lineToOutput(...),
            $order->getLines()->toArray(),
        );
        $rejectedLines = $this->rejectedLinesFor($order);

        $slot = $order->getPickupSlot();

        return new OrderOutput(
            id: $order->getId()->toRfc4122(),
            kadhiaId: $order->getKadhia()?->getId()->toRfc4122(),
            storeId: $order->getShop()->getId()->toRfc4122(),
            storeName: $order->getShop()->getName(),
            storeAddress: $order->getShop()->getAddress(),
            storeCity: $order->getShop()->getCity(),
            orderNumber: $order->getOrderNumber(),
            orderNumberDisplay: $order->getOrderNumberDisplay(),
            status: $order->getStatus()->value,
            totalTnd: $order->getTotalTnd(),
            pickupSlotId: $slot?->getId()->toRfc4122(),
            pickupSlot: null === $slot ? null : [
                'id' => $slot->getId()->toRfc4122(),
                'starts_at' => PickupSlotDisplayTime::toLocalAtom($slot->getStartsAt()),
                'ends_at' => PickupSlotDisplayTime::toLocalAtom($slot->getEndsAt()),
            ],
            notes: $order->getNotes(),
            lines: $lines,
            rejectionReason: $order->getRejectionReason(),
            rejectedLines: $rejectedLines,
            createdAt: $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            pickupCode: $order->getPickupCode(),
        );
    }

    private static function lineToOutput(OrderLine $line): OrderLineOutput
    {
        return new OrderLineOutput(
            merchantProductId: $line->getMerchantProduct()->getId()->toRfc4122(),
            productName: $line->getMerchantProduct()->getDisplayNameFr(),
            quantity: $line->getQuantity(),
            unitPriceTnd: $line->getUnitPriceTnd(),
            lineTotalTnd: $line->getLineTotalTnd(),
        );
    }

    /**
     * @return list<OrderLineOutput>
     */
    private function rejectedLinesFor(Order $order): array
    {
        if (OrderStatus::PartiallyAccepted !== $order->getStatus()) {
            return [];
        }

        $rejectedProductIds = array_fill_keys(
            $order->getPartialAcceptanceRejectedMerchantProductIds(),
            true,
        );
        if ([] === $rejectedProductIds) {
            return [];
        }

        $rejectedLines = [];
        foreach ($order->getLines() as $line) {
            $merchantProductId = $line->getMerchantProduct()->getId()->toRfc4122();
            if (isset($rejectedProductIds[$merchantProductId])) {
                $rejectedLines[] = self::lineToOutput($line);
            }
        }

        return $rejectedLines;
    }
}
