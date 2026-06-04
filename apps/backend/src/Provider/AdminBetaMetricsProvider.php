<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminBetaMetricsOutput;
use App\ApiResource\AdminBetaMetricsStoreOutput;
use App\Entity\OrderStatusLog;
use App\Entity\Shop;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminBetaMetricsOutput>
 */
final readonly class AdminBetaMetricsProvider implements ProviderInterface
{
    private const string ERROR_INVALID_DATE_FROM = 'ADMIN_BETA_METRICS_INVALID_DATE_FROM';
    private const string ERROR_INVALID_DATE_TO = 'ADMIN_BETA_METRICS_INVALID_DATE_TO';
    private const string ERROR_INVERTED_PERIOD = 'ADMIN_BETA_METRICS_INVERTED_PERIOD';
    private const string ERROR_INVALID_STORE = 'ADMIN_BETA_METRICS_INVALID_STORE';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminBetaMetricsOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Africa/Tunis'));
        $dateFrom = $this->parseDate($request?->query->get('date_from'), $today->modify('-30 days'), self::ERROR_INVALID_DATE_FROM);
        $dateTo = $this->parseDate($request?->query->get('date_to'), $today, self::ERROR_INVALID_DATE_TO);
        if ($dateFrom > $dateTo) {
            throw new BadRequestHttpException(self::ERROR_INVERTED_PERIOD);
        }

        $storeId = $request?->query->get('store_id') ?: null;
        if (null !== $storeId && !Uuid::isValid((string) $storeId)) {
            throw new BadRequestHttpException(self::ERROR_INVALID_STORE);
        }
        $storeId = null === $storeId ? null : (string) $storeId;

        $periodStart = $dateFrom;
        $periodEndExclusive = $dateTo->modify('+1 day');
        $rows = $this->fetchStatusRows($periodStart, $periodEndExclusive, $storeId);
        $stores = $this->buildStoreOutputs($rows);
        $totals = $this->sumStores($stores);
        $activeStores = $this->countActiveStores($storeId);
        $activatedStores = $this->countActivatedStores($stores);

        return new AdminBetaMetricsOutput(
            id: 'admin-beta-metrics',
            dateFrom: $dateFrom->format('Y-m-d'),
            dateTo: $dateTo->format('Y-m-d'),
            storeId: $storeId,
            submitted: $totals['submitted'],
            accepted: $totals['accepted'],
            partiallyAccepted: $totals['partially_accepted'],
            rejected: $totals['rejected'],
            cancelled: $totals['cancelled'],
            completed: $totals['completed'],
            acceptanceRate: $this->rate($totals['accepted'] + $totals['partially_accepted'], $totals['submitted']),
            cancellationRate: $this->rate($totals['cancelled'], $totals['submitted']),
            activeStores: $activeStores,
            activatedStores: $activatedStores,
            storeActivationRate: $this->rate($activatedStores, $activeStores),
            stores: $stores,
        );
    }

    private function parseDate(mixed $raw, \DateTimeImmutable $default, string $errorCode): \DateTimeImmutable
    {
        if (null === $raw || '' === $raw) {
            return $default;
        }

        if (!\is_string($raw)) {
            throw new BadRequestHttpException($errorCode);
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new \DateTimeZone('Africa/Tunis'));
        if (!$date instanceof \DateTimeImmutable || $date->format('Y-m-d') !== $raw) {
            throw new BadRequestHttpException($errorCode);
        }

        return $date;
    }

    /**
     * @return list<array{
     *     store_id: mixed,
     *     store_name: string,
     *     status: OrderStatus|string,
     *     count: int|string,
     *     last_activity_at: \DateTimeImmutable|null
     * }>
     */
    private function fetchStatusRows(\DateTimeImmutable $dateFrom, \DateTimeImmutable $dateTo, ?string $storeId): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(orderEntity.shop) AS store_id')
            ->addSelect('shop.name AS store_name')
            ->addSelect('log.status AS status')
            ->addSelect('COUNT(log.id) AS count')
            ->addSelect('MAX(log.createdAt) AS last_activity_at')
            ->from(OrderStatusLog::class, 'log')
            ->join('log.order', 'orderEntity')
            ->join('orderEntity.shop', 'shop')
            ->andWhere('log.createdAt >= :dateFrom')
            ->andWhere('log.createdAt < :dateTo')
            ->andWhere('log.status IN (:statuses)')
            ->groupBy('shop.id')
            ->addGroupBy('shop.name')
            ->addGroupBy('log.status')
            ->orderBy('MAX(log.createdAt)', 'DESC')
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->setParameter('statuses', [
                OrderStatus::Submitted,
                OrderStatus::Accepted,
                OrderStatus::PartiallyAccepted,
                OrderStatus::Rejected,
                OrderStatus::Cancelled,
                OrderStatus::Completed,
            ]);

        if (null !== $storeId) {
            $queryBuilder
                ->andWhere('shop.id = :storeId')
                ->setParameter('storeId', $storeId, 'uuid');
        }

        /** @var list<array{store_id: mixed, store_name: string, status: OrderStatus|string, count: int|string, last_activity_at: \DateTimeImmutable|null}> $rows */
        $rows = $queryBuilder->getQuery()->getResult();

        return $rows;
    }

    /**
     * @param list<array{store_id: mixed, store_name: string, status: OrderStatus|string, count: int|string, last_activity_at: \DateTimeImmutable|null}> $rows
     *
     * @return list<AdminBetaMetricsStoreOutput>
     */
    private function buildStoreOutputs(array $rows): array
    {
        /** @var array<string, array{store_id: string, store_name: string, counts: array<string, int>, last_activity_at: \DateTimeImmutable|null}> $stores */
        $stores = [];

        foreach ($rows as $row) {
            $storeId = $this->normalizeUuid($row['store_id']);
            $status = $row['status'] instanceof OrderStatus ? $row['status']->value : (string) $row['status'];
            $stores[$storeId] ??= [
                'store_id' => $storeId,
                'store_name' => $row['store_name'],
                'counts' => $this->emptyCounts(),
                'last_activity_at' => null,
            ];
            $stores[$storeId]['counts'][$status] = (int) $row['count'];
            $lastActivityAt = $this->normalizeDateTime($row['last_activity_at']);
            if ($lastActivityAt instanceof \DateTimeImmutable) {
                $currentLastActivityAt = $stores[$storeId]['last_activity_at'];
                if (!$currentLastActivityAt instanceof \DateTimeImmutable || $lastActivityAt > $currentLastActivityAt) {
                    $stores[$storeId]['last_activity_at'] = $lastActivityAt;
                }
            }
        }

        uasort($stores, static function (array $left, array $right): int {
            $leftDate = $left['last_activity_at'];
            $rightDate = $right['last_activity_at'];
            if ($leftDate instanceof \DateTimeImmutable && $rightDate instanceof \DateTimeImmutable) {
                return $rightDate <=> $leftDate;
            }

            return $left['store_name'] <=> $right['store_name'];
        });

        return array_values(array_map(function (array $store): AdminBetaMetricsStoreOutput {
            $counts = $store['counts'];

            return new AdminBetaMetricsStoreOutput(
                storeId: $store['store_id'],
                storeName: $store['store_name'],
                submitted: $counts[OrderStatus::Submitted->value],
                accepted: $counts[OrderStatus::Accepted->value],
                partiallyAccepted: $counts[OrderStatus::PartiallyAccepted->value],
                rejected: $counts[OrderStatus::Rejected->value],
                cancelled: $counts[OrderStatus::Cancelled->value],
                completed: $counts[OrderStatus::Completed->value],
                acceptanceRate: $this->rate(
                    $counts[OrderStatus::Accepted->value] + $counts[OrderStatus::PartiallyAccepted->value],
                    $counts[OrderStatus::Submitted->value],
                ),
                cancellationRate: $this->rate($counts[OrderStatus::Cancelled->value], $counts[OrderStatus::Submitted->value]),
                lastActivityAt: $store['last_activity_at']?->format(\DateTimeInterface::ATOM),
            );
        }, $stores));
    }

    /**
     * @param list<AdminBetaMetricsStoreOutput> $stores
     *
     * @return array{submitted: int, accepted: int, partially_accepted: int, rejected: int, cancelled: int, completed: int}
     */
    private function sumStores(array $stores): array
    {
        $totals = $this->emptyCounts();
        foreach ($stores as $store) {
            $totals[OrderStatus::Submitted->value] += $store->submitted;
            $totals[OrderStatus::Accepted->value] += $store->accepted;
            $totals[OrderStatus::PartiallyAccepted->value] += $store->partiallyAccepted;
            $totals[OrderStatus::Rejected->value] += $store->rejected;
            $totals[OrderStatus::Cancelled->value] += $store->cancelled;
            $totals[OrderStatus::Completed->value] += $store->completed;
        }

        return [
            'submitted' => $totals[OrderStatus::Submitted->value],
            'accepted' => $totals[OrderStatus::Accepted->value],
            'partially_accepted' => $totals[OrderStatus::PartiallyAccepted->value],
            'rejected' => $totals[OrderStatus::Rejected->value],
            'cancelled' => $totals[OrderStatus::Cancelled->value],
            'completed' => $totals[OrderStatus::Completed->value],
        ];
    }

    private function countActiveStores(?string $storeId): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('COUNT(shop.id)')
            ->from(Shop::class, 'shop')
            ->andWhere('shop.active = true')
            ->andWhere('shop.archivedAt IS NULL');

        if (null !== $storeId) {
            $queryBuilder
                ->andWhere('shop.id = :storeId')
                ->setParameter('storeId', $storeId, 'uuid');
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<AdminBetaMetricsStoreOutput> $stores
     */
    private function countActivatedStores(array $stores): int
    {
        return \count(array_filter($stores, static fn (AdminBetaMetricsStoreOutput $store): bool => $store->completed > 0));
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return [
            OrderStatus::Submitted->value => 0,
            OrderStatus::Accepted->value => 0,
            OrderStatus::PartiallyAccepted->value => 0,
            OrderStatus::Rejected->value => 0,
            OrderStatus::Cancelled->value => 0,
            OrderStatus::Completed->value => 0,
        ];
    }

    private function rate(int $numerator, int $denominator): float
    {
        if (0 === $denominator) {
            return 0.0;
        }

        return round($numerator / $denominator, 3);
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

    private function normalizeDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if (\is_string($value) && '' !== $value) {
            return new \DateTimeImmutable($value, new \DateTimeZone('Africa/Tunis'));
        }

        return null;
    }
}
