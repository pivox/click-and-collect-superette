<?php

declare(strict_types=1);

namespace App\Mapper;

use App\ApiResource\MerchantProductPriceHistoryItemOutput;
use App\ApiResource\MerchantProductPriceHistoryOutput;
use App\ApiResource\MerchantProductPriceUpdateOutput;
use App\Entity\MerchantProduct;
use App\Entity\MerchantProductPriceHistory;

final readonly class MerchantProductPriceHistoryMapper
{
    /**
     * @param list<MerchantProductPriceHistory> $items
     */
    public function toHistoryOutput(MerchantProduct $merchantProduct, array $items): MerchantProductPriceHistoryOutput
    {
        return new MerchantProductPriceHistoryOutput(
            merchantProductId: $merchantProduct->getId()->toRfc4122(),
            currentPrice: $merchantProduct->getPriceTnd(),
            currency: [] === $items ? 'TND' : $items[0]->getCurrency(),
            priceHistory: array_map($this->toItemOutput(...), $items),
        );
    }

    public function toUpdateOutput(
        MerchantProduct $merchantProduct,
        string $currency,
        ?MerchantProductPriceHistory $lastPriceChange,
    ): MerchantProductPriceUpdateOutput {
        return new MerchantProductPriceUpdateOutput(
            id: $merchantProduct->getId()->toRfc4122(),
            currentPrice: $merchantProduct->getPriceTnd(),
            currency: strtoupper($currency),
            lastPriceChange: null === $lastPriceChange ? null : $this->toItemOutput($lastPriceChange),
        );
    }

    public function toItemOutput(MerchantProductPriceHistory $history): MerchantProductPriceHistoryItemOutput
    {
        return new MerchantProductPriceHistoryItemOutput(
            oldPrice: $history->getOldPrice(),
            newPrice: $history->getNewPrice(),
            currency: $history->getCurrency(),
            changeType: $history->getChangeType()->value,
            source: $history->getSource()->value,
            reason: $history->getReason(),
            changedByUserId: $history->getChangedByUser()?->getId()->toRfc4122(),
            changedAt: $history->getChangedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
