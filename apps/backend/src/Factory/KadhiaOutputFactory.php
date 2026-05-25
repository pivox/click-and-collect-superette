<?php

declare(strict_types=1);

namespace App\Factory;

use App\ApiResource\KadhiaLineOutput;
use App\ApiResource\KadhiaOutput;
use App\Entity\Kadhia;
use App\Entity\KadhiaLine;

final readonly class KadhiaOutputFactory
{
    public function toOutput(Kadhia $kadhia, ?string $orderId = null): KadhiaOutput
    {
        $lines = array_map(
            static fn (KadhiaLine $l): KadhiaLineOutput => new KadhiaLineOutput(
                id: $l->getId()->toRfc4122(),
                merchantProductId: $l->getMerchantProduct()->getId()->toRfc4122(),
                productName: $l->getMerchantProduct()->getDisplayNameFr(),
                unitPriceTnd: $l->getUnitPriceTnd(),
                quantity: $l->getQuantity(),
                subtotalTnd: bcmul($l->getUnitPriceTnd(), (string) $l->getQuantity(), 3),
            ),
            $kadhia->getLines()->toArray(),
        );

        $totalTnd = array_reduce(
            $lines,
            static fn (string $carry, KadhiaLineOutput $l): string => bcadd($carry, $l->subtotalTnd, 3),
            '0.000',
        );

        return new KadhiaOutput(
            id: $kadhia->getId()->toRfc4122(),
            storeId: $kadhia->getShop()->getId()->toRfc4122(),
            status: $kadhia->getStatus()->value,
            orderId: $orderId,
            notes: $kadhia->getNotes(),
            lines: $lines,
            totalTnd: $totalTnd,
            createdAt: $kadhia->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $kadhia->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
