<?php

declare(strict_types=1);

namespace App\Factory;

use App\ApiResource\OrderLineOutput;
use App\ApiResource\OrderOutput;
use App\Entity\Order;
use App\Entity\OrderLine;

final readonly class OrderOutputFactory
{
    public function toOutput(Order $order): OrderOutput
    {
        $lines = array_map(
            static fn (OrderLine $l): OrderLineOutput => new OrderLineOutput(
                merchantProductId: $l->getMerchantProduct()->getId()->toRfc4122(),
                quantity: $l->getQuantity(),
                unitPriceTnd: $l->getUnitPriceTnd(),
                lineTotalTnd: $l->getLineTotalTnd(),
            ),
            $order->getLines()->toArray(),
        );

        $slot = $order->getPickupSlot();

        return new OrderOutput(
            id: $order->getId()->toRfc4122(),
            storeId: $order->getShop()->getId()->toRfc4122(),
            status: $order->getStatus()->value,
            totalTnd: $order->getTotalTnd(),
            pickupSlotId: $slot?->getId()->toRfc4122(),
            notes: $order->getNotes(),
            lines: $lines,
            createdAt: $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
