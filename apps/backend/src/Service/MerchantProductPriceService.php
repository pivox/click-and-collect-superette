<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MerchantProduct;
use App\Entity\MerchantProductPriceHistory;
use App\Entity\User;
use App\Enum\MerchantProductPriceChangeType;
use App\Enum\MerchantProductPriceSource;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MerchantProductPriceService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function recordInitialPrice(
        MerchantProduct $merchantProduct,
        MerchantProductPriceSource $source,
        ?User $changedByUser = null,
        ?string $reason = null,
        string $currency = 'TND',
        ?\DateTimeImmutable $changedAt = null,
    ): MerchantProductPriceHistory {
        $history = $this->createHistory(
            merchantProduct: $merchantProduct,
            oldPrice: null,
            newPrice: $this->normalizePrice($merchantProduct->getPriceTnd()),
            currency: $currency,
            changeType: MerchantProductPriceChangeType::Initial,
            source: $source,
            changedByUser: $changedByUser,
            reason: $reason,
            changedAt: $changedAt,
        );

        $this->entityManager->persist($history);

        return $history;
    }

    public function changePrice(
        MerchantProduct $merchantProduct,
        string $newPrice,
        MerchantProductPriceChangeType $changeType,
        MerchantProductPriceSource $source,
        ?User $changedByUser = null,
        ?string $reason = null,
        string $currency = 'TND',
        ?\DateTimeImmutable $changedAt = null,
    ): ?MerchantProductPriceHistory {
        if (MerchantProductPriceChangeType::Initial === $changeType) {
            throw new \InvalidArgumentException('Initial price changes must use recordInitialPrice().');
        }

        $oldPrice = $this->normalizePrice($merchantProduct->getPriceTnd());
        $normalizedNewPrice = $this->normalizePrice($newPrice);

        if (0 === bccomp($oldPrice, $normalizedNewPrice, 3)) {
            return null;
        }

        $history = $this->createHistory(
            merchantProduct: $merchantProduct,
            oldPrice: $oldPrice,
            newPrice: $normalizedNewPrice,
            currency: $currency,
            changeType: $changeType,
            source: $source,
            changedByUser: $changedByUser,
            reason: $reason,
            changedAt: $changedAt,
        );

        $merchantProduct->setPriceTnd($normalizedNewPrice);
        $this->entityManager->persist($history);

        return $history;
    }

    private function createHistory(
        MerchantProduct $merchantProduct,
        ?string $oldPrice,
        string $newPrice,
        string $currency,
        MerchantProductPriceChangeType $changeType,
        MerchantProductPriceSource $source,
        ?User $changedByUser,
        ?string $reason,
        ?\DateTimeImmutable $changedAt,
    ): MerchantProductPriceHistory {
        return new MerchantProductPriceHistory(
            merchantProduct: $merchantProduct,
            merchant: $merchantProduct->getShop()->getOwner(),
            oldPrice: $oldPrice,
            newPrice: $newPrice,
            currency: $this->normalizeCurrency($currency),
            changeType: $changeType,
            source: $source,
            changedByUser: $changedByUser,
            reason: $this->normalizeReason($reason),
            changedAt: $changedAt,
        );
    }

    private function normalizePrice(string $price): string
    {
        $normalizedPrice = bcadd(trim($price), '0', 3);
        if (bccomp($normalizedPrice, '0.000', 3) <= 0) {
            throw new \InvalidArgumentException('PRICE_MUST_BE_POSITIVE');
        }

        return $normalizedPrice;
    }

    private function normalizeCurrency(string $currency): string
    {
        $normalizedCurrency = strtoupper(trim($currency));
        if ('' === $normalizedCurrency) {
            throw new \InvalidArgumentException('CURRENCY_MUST_NOT_BE_EMPTY');
        }
        if (3 !== \strlen($normalizedCurrency)) {
            throw new \InvalidArgumentException('CURRENCY_MUST_HAVE_THREE_LETTERS');
        }

        return $normalizedCurrency;
    }

    private function normalizeReason(?string $reason): ?string
    {
        if (null === $reason) {
            return null;
        }

        $reason = trim($reason);

        return '' === $reason ? null : $reason;
    }
}
