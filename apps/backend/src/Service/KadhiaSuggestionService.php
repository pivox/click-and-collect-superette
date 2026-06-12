<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Enum\ProductReferenceStatus;

/**
 * Pure suggestion heuristics for the Kadhia (issue #383).
 *
 * Works on already-fetched entities so the logic stays unit-testable and
 * portable (SQLite tests / PostgreSQL production). Volumes are MVP-small:
 * callers cap the order history before invoking these methods.
 */
final readonly class KadhiaSuggestionService
{
    /**
     * Products co-occurring (in the customer's own order history) with the
     * products currently in the Kadhia, ranked by co-occurrence frequency.
     *
     * @param list<Order>  $orders         customer orders for the shop, newest first
     * @param list<string> $seedProductIds merchant product ids of the current Kadhia lines
     *
     * @return list<MerchantProduct>
     */
    public function buildFrequentlyBoughtTogether(array $orders, array $seedProductIds, int $limit): array
    {
        if ([] === $seedProductIds) {
            return [];
        }

        $seedIndex = array_flip($seedProductIds);
        $frequencies = [];
        $productsById = [];

        foreach ($orders as $order) {
            $orderProducts = [];
            $containsSeed = false;
            foreach ($order->getLines() as $line) {
                $product = $line->getMerchantProduct();
                $productId = $product->getId()->toRfc4122();
                $orderProducts[$productId] = $product;
                if (isset($seedIndex[$productId])) {
                    $containsSeed = true;
                }
            }

            if (!$containsSeed) {
                continue;
            }

            // One increment per order (not per quantity) to reward recurrence.
            foreach ($orderProducts as $productId => $product) {
                if (isset($seedIndex[$productId])) {
                    continue;
                }

                $frequencies[$productId] = ($frequencies[$productId] ?? 0) + 1;
                $productsById[$productId] = $product;
            }
        }

        $suggestions = array_values(array_filter(
            $productsById,
            fn (MerchantProduct $product): bool => $this->isSuggestible($product),
        ));

        usort(
            $suggestions,
            static function (MerchantProduct $left, MerchantProduct $right) use ($frequencies): int {
                $leftId = $left->getId()->toRfc4122();
                $rightId = $right->getId()->toRfc4122();

                return ($frequencies[$rightId] <=> $frequencies[$leftId])
                    ?: ($left->getDisplayNameFr() <=> $right->getDisplayNameFr());
            },
        );

        return \array_slice($suggestions, 0, $limit);
    }

    /**
     * Distinct products from the customer's most recent orders, newest first.
     *
     * @param list<Order>  $orders             customer orders for the shop, newest first
     * @param list<string> $excludedProductIds merchant product ids to skip (current Kadhia lines)
     *
     * @return list<MerchantProduct>
     */
    public function buildRecentlyOrdered(array $orders, array $excludedProductIds, int $limit): array
    {
        $excludedIndex = array_flip($excludedProductIds);
        $seen = [];
        $suggestions = [];

        foreach ($orders as $order) {
            foreach ($order->getLines() as $line) {
                $product = $line->getMerchantProduct();
                $productId = $product->getId()->toRfc4122();

                if (isset($seen[$productId]) || isset($excludedIndex[$productId])) {
                    continue;
                }
                $seen[$productId] = true;

                if (!$this->isSuggestible($product)) {
                    continue;
                }

                $suggestions[] = $product;
                if (\count($suggestions) >= $limit) {
                    return $suggestions;
                }
            }
        }

        return $suggestions;
    }

    /**
     * Available products of the same category and shop, ranked by price
     * proximity to the unavailable line's snapshot price.
     *
     * @param string                $lineUnitPriceTnd   snapshot price of the Kadhia line being replaced
     * @param list<MerchantProduct> $candidates         orderable products of the shop (public catalog rules)
     * @param list<string>          $excludedProductIds merchant product ids already in the Kadhia
     *
     * @return list<MerchantProduct>
     */
    public function findReplacementsFor(
        MerchantProduct $unavailable,
        string $lineUnitPriceTnd,
        array $candidates,
        array $excludedProductIds,
        int $limit = 3,
    ): array {
        $excludedIndex = array_flip($excludedProductIds);
        $unavailableId = $unavailable->getId()->toRfc4122();
        $categorySlug = $unavailable->getDisplayCategorySlug();

        $alternatives = array_values(array_filter(
            $candidates,
            static function (MerchantProduct $candidate) use ($unavailableId, $categorySlug, $excludedIndex): bool {
                $candidateId = $candidate->getId()->toRfc4122();

                return $candidateId !== $unavailableId
                    && !isset($excludedIndex[$candidateId])
                    && $candidate->getDisplayCategorySlug() === $categorySlug;
            },
        ));

        usort(
            $alternatives,
            static function (MerchantProduct $left, MerchantProduct $right) use ($lineUnitPriceTnd): int {
                $leftGap = self::priceGap($left->getEffectivePriceTnd(), $lineUnitPriceTnd);
                $rightGap = self::priceGap($right->getEffectivePriceTnd(), $lineUnitPriceTnd);

                return bccomp($leftGap, $rightGap, 3)
                    ?: ($left->getDisplayNameFr() <=> $right->getDisplayNameFr());
            },
        );

        return \array_slice($alternatives, 0, $limit);
    }

    /**
     * Mirrors the public catalog orderability rules
     * (MerchantProductRepository::findPublicCatalogForShop).
     */
    public function isSuggestible(MerchantProduct $product): bool
    {
        if (!$product->isAvailable() || !$product->isVisible()) {
            return false;
        }

        if (bccomp($product->getPriceTnd(), '0.000', 3) <= 0) {
            return false;
        }

        $reference = $product->getProductReference();

        return null === $reference || ProductReferenceStatus::Approved === $reference->getStatus();
    }

    private static function priceGap(string $price, string $referencePrice): string
    {
        $gap = bcsub($price, $referencePrice, 3);

        return str_starts_with($gap, '-') ? substr($gap, 1) : $gap;
    }
}
