<?php

declare(strict_types=1);

namespace App\Service;

use App\ApiResource\AdminStoreActivationChecklistOutput;
use App\ApiResource\AdminStoreActivationChecklistStepOutput;
use App\Entity\Shop;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class StoreActivationChecklistCalculator
{
    private const string PICKUP_CODE_COMPLETION_NOTE = 'withdrawal_validated_by_code';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private int $minimumCatalogProducts = 5,
    ) {
    }

    public function calculate(Shop $shop): AdminStoreActivationChecklistOutput
    {
        return $this->calculateMany([$shop])[$shop->getId()->toRfc4122()];
    }

    /**
     * @param list<Shop> $shops
     *
     * @return array<string, AdminStoreActivationChecklistOutput>
     */
    public function calculateMany(array $shops): array
    {
        if ([] === $shops) {
            return [];
        }

        $catalogProductsByShopId = $this->countCatalogProductsGrouped($shops);
        $shopsWithPickupSlots = $this->findShopsWithPickupSlots($shops);
        $shopsWithTestOrders = $this->findShopsWithTestOrders($shops);
        $shopsWithValidatedPickup = $this->findShopsWithValidatedPickup($shops);
        $outputs = [];

        foreach ($shops as $shop) {
            $shopId = $shop->getId()->toRfc4122();
            $outputs[$shopId] = $this->buildOutput(
                shop: $shop,
                catalogProducts: $catalogProductsByShopId[$shopId] ?? 0,
                hasPickupSlots: $shopsWithPickupSlots[$shopId] ?? false,
                hasTestOrder: $shopsWithTestOrders[$shopId] ?? false,
                hasValidatedPickup: $shopsWithValidatedPickup[$shopId] ?? false,
            );
        }

        return $outputs;
    }

    private function buildOutput(
        Shop $shop,
        int $catalogProducts,
        bool $hasPickupSlots,
        bool $hasTestOrder,
        bool $hasValidatedPickup,
    ): AdminStoreActivationChecklistOutput {
        $steps = [
            new AdminStoreActivationChecklistStepOutput(
                key: 'merchant_active',
                label: 'Marchand actif',
                completed: $this->hasActiveMerchant($shop),
                required: true,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'store_active',
                label: 'Supérette active',
                completed: $shop->isActive() && null === $shop->getArchivedAt(),
                required: true,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'opening_hours',
                label: 'Horaires renseignés',
                completed: $this->hasOpeningHours($shop),
                required: true,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'pickup_slots',
                label: 'Créneaux créés',
                completed: $hasPickupSlots,
                required: true,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'catalog_minimum',
                label: 'Catalogue minimum',
                completed: $catalogProducts >= $this->minimumCatalogProducts,
                required: true,
                currentValue: $catalogProducts,
                targetValue: $this->minimumCatalogProducts,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'qr_code',
                label: 'QR code accessible',
                completed: $shop->isActive() && null === $shop->getArchivedAt() && '' !== trim($shop->getQrCodeToken()),
                required: true,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'test_order',
                label: 'Commande test passée',
                completed: $hasTestOrder,
                required: true,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'test_pickup',
                label: 'Retrait test validé',
                completed: $hasValidatedPickup,
                required: true,
            ),
        ];

        $requiredTotal = \count(array_filter($steps, static fn (AdminStoreActivationChecklistStepOutput $step): bool => $step->required));
        $requiredCompleted = \count(array_filter($steps, static fn (AdminStoreActivationChecklistStepOutput $step): bool => $step->required && $step->completed));

        return new AdminStoreActivationChecklistOutput(
            storeId: $shop->getId()->toRfc4122(),
            storeName: $shop->getName(),
            ready: $requiredCompleted === $requiredTotal,
            minimumCatalogProducts: $this->minimumCatalogProducts,
            requiredCompletedCount: $requiredCompleted,
            requiredTotalCount: $requiredTotal,
            steps: $steps,
        );
    }

    private function hasActiveMerchant(Shop $shop): bool
    {
        $owner = $shop->getOwner();

        return null !== $owner && $owner->isActive() && \in_array('ROLE_MERCHANT', $owner->getRoles(), true);
    }

    private function hasOpeningHours(Shop $shop): bool
    {
        $openingHours = $shop->getOpeningHours();
        if (!\is_array($openingHours)) {
            return false;
        }

        $weekly = $openingHours['weekly'] ?? null;
        if (!\is_array($weekly)) {
            return false;
        }

        foreach ($weekly as $daySlots) {
            if (!\is_array($daySlots)) {
                continue;
            }

            foreach ($daySlots as $slot) {
                if (
                    \is_array($slot)
                    && isset($slot['start'], $slot['end'])
                    && '' !== trim((string) $slot['start'])
                    && '' !== trim((string) $slot['end'])
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<Shop> $shops
     *
     * @return array<string, bool>
     */
    private function findShopsWithPickupSlots(array $shops): array
    {
        [$placeholders, $shopParams, $shopTypes] = $this->shopSqlParameters($shops);
        $rows = $this->entityManager->getConnection()->executeQuery(
            \sprintf(
                'SELECT shop_id FROM pickup_slot_rules WHERE shop_id IN (%s) AND is_active = true GROUP BY shop_id',
                $placeholders,
            ),
            $shopParams,
            $shopTypes,
        )->fetchAllAssociative();

        $withPickupSlots = $this->boolMapFromRows($rows);
        $slotRows = $this->entityManager->getConnection()->executeQuery(
            \sprintf(
                'SELECT shop_id FROM pickup_slots WHERE shop_id IN (%s) AND is_active = true AND starts_at > ? AND booked_count < capacity GROUP BY shop_id',
                $placeholders,
            ),
            [...$shopParams, new \DateTimeImmutable()],
            [...$shopTypes, 'datetime_immutable'],
        )->fetchAllAssociative();

        foreach ($this->boolMapFromRows($slotRows) as $shopId => $hasSlots) {
            $withPickupSlots[$shopId] = $hasSlots;
        }

        return $withPickupSlots;
    }

    /**
     * @param list<Shop> $shops
     *
     * @return array<string, int>
     */
    private function countCatalogProductsGrouped(array $shops): array
    {
        [$placeholders, $shopParams, $shopTypes] = $this->shopSqlParameters($shops);
        $rows = $this->entityManager->getConnection()->executeQuery(
            \sprintf(
                'SELECT shop_id, COUNT(id) AS products_count FROM merchant_products WHERE shop_id IN (%s) AND is_visible = true AND is_available = true GROUP BY shop_id',
                $placeholders,
            ),
            $shopParams,
            $shopTypes,
        )->fetchAllAssociative();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$this->normalizeUuid($row['shop_id'])] = (int) $row['products_count'];
        }

        return $counts;
    }

    /**
     * @param list<Shop> $shops
     *
     * @return array<string, bool>
     */
    private function findShopsWithTestOrders(array $shops): array
    {
        [$placeholders, $shopParams, $shopTypes] = $this->shopSqlParameters($shops);
        $rows = $this->entityManager->getConnection()->executeQuery(
            \sprintf(
                'SELECT shop_id FROM orders WHERE shop_id IN (%s) AND status IN (?, ?, ?) GROUP BY shop_id',
                $placeholders,
            ),
            [
                ...$shopParams,
                OrderStatus::Ready->value,
                OrderStatus::PickupPending->value,
                OrderStatus::Completed->value,
            ],
            [...$shopTypes, 'string', 'string', 'string'],
        )->fetchAllAssociative();

        return $this->boolMapFromRows($rows);
    }

    /**
     * @param list<Shop> $shops
     *
     * @return array<string, bool>
     */
    private function findShopsWithValidatedPickup(array $shops): array
    {
        [$placeholders, $shopParams, $shopTypes] = $this->shopSqlParameters($shops);
        $rows = $this->entityManager->getConnection()->executeQuery(
            \sprintf(
                <<<'SQL'
SELECT orders.shop_id
FROM pickup_sessions
INNER JOIN orders ON pickup_sessions.order_id = orders.id
LEFT JOIN order_status_logs AS pickup_code_log
    ON pickup_code_log.order_id = orders.id
    AND pickup_code_log.status = ?
    AND pickup_code_log.note = ?
WHERE orders.shop_id IN (%s)
    AND orders.status = ?
    AND pickup_sessions.used = true
    AND pickup_sessions.merchant_confirmed_at IS NOT NULL
    AND pickup_sessions.customer_confirmed_at IS NOT NULL
    AND pickup_sessions.force_completed_by_merchant = false
    AND pickup_code_log.id IS NULL
GROUP BY orders.shop_id
SQL,
                $placeholders,
            ),
            [
                OrderStatus::Completed->value,
                self::PICKUP_CODE_COMPLETION_NOTE,
                ...$shopParams,
                OrderStatus::Completed->value,
            ],
            ['string', 'string', ...$shopTypes, 'string'],
        )->fetchAllAssociative();

        return $this->boolMapFromRows($rows);
    }

    /**
     * @param list<Shop> $shops
     *
     * @return array{0: string, 1: list<string>, 2: list<string>}
     */
    private function shopSqlParameters(array $shops): array
    {
        return [
            implode(', ', array_fill(0, \count($shops), '?')),
            array_map(static fn (Shop $shop): string => $shop->getId()->toRfc4122(), $shops),
            array_fill(0, \count($shops), 'uuid'),
        ];
    }

    /**
     * @param list<array{shop_id: mixed}> $rows
     *
     * @return array<string, bool>
     */
    private function boolMapFromRows(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$this->normalizeUuid($row['shop_id'])] = true;
        }

        return $map;
    }

    private function normalizeUuid(mixed $value): string
    {
        if ($value instanceof Uuid) {
            return $value->toRfc4122();
        }

        if (\is_string($value) && 16 === \strlen($value)) {
            return Uuid::fromBinary($value)->toRfc4122();
        }

        return (string) $value;
    }
}
