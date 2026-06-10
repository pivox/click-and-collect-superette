<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantStatisticsOutput;
use App\ApiResource\MerchantStatisticsTopProductOutput;
use App\ApiResource\MerchantStatisticsTopSlotOutput;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\Shop;
use App\Enum\OrderStatus;
use App\Security\MerchantShopAccessChecker;
use App\Service\PickupSlotDisplayTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<MerchantStatisticsOutput>
 */
final readonly class MerchantStatisticsProvider implements ProviderInterface
{
    private const string ERROR_INVALID_DATE_FROM = 'MERCHANT_STATS_INVALID_DATE_FROM';
    private const string ERROR_INVALID_DATE_TO = 'MERCHANT_STATS_INVALID_DATE_TO';
    private const string ERROR_INVERTED_PERIOD = 'MERCHANT_STATS_INVERTED_PERIOD';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantStatisticsOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        $store = $this->entityManager->find(Shop::class, $storeId);
        if (null === $store) {
            throw new \InvalidArgumentException('Shop not found');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($store);

        $request = $this->requestStack->getCurrentRequest();
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Africa/Tunis'));
        $dateFrom = $this->parseDate($request?->query->get('date_from'), $today->modify('-30 days'), self::ERROR_INVALID_DATE_FROM);
        $dateTo = $this->parseDate($request?->query->get('date_to'), $today, self::ERROR_INVALID_DATE_TO);
        if ($dateFrom > $dateTo) {
            throw new BadRequestHttpException(self::ERROR_INVERTED_PERIOD);
        }

        $periodStart = $dateFrom;
        $periodEndExclusive = $dateTo->modify('+1 day');

        $ordersSummary = $this->queryOrdersSummary($store, $periodStart, $periodEndExclusive);
        $ordersByStatus = $this->queryOrdersByStatus($store, $periodStart, $periodEndExclusive);
        $topProducts = $this->queryTopProducts($store, $periodStart, $periodEndExclusive);
        $topSlots = $this->queryTopSlots($store, $periodStart, $periodEndExclusive);

        $totalSubmitted = array_reduce($ordersByStatus, static fn (int $carry, array $row): int => $carry + (int) $row['count'], 0);
        $totalAcceptedAndPartial = ((int) ($ordersByStatus[OrderStatus::Accepted->value]['count'] ?? 0))
            + ((int) ($ordersByStatus[OrderStatus::PartiallyAccepted->value]['count'] ?? 0));
        $totalCancelled = (int) ($ordersByStatus[OrderStatus::Cancelled->value]['count'] ?? 0);
        $totalRejected = (int) ($ordersByStatus[OrderStatus::Rejected->value]['count'] ?? 0);

        return new MerchantStatisticsOutput(
            id: $storeId,
            storeId: $storeId,
            dateFrom: $dateFrom->format('Y-m-d'),
            dateTo: $dateTo->format('Y-m-d'),
            totalOrders: (int) ($ordersSummary['total_orders'] ?? 0),
            totalRevenueTnd: (string) ($ordersSummary['total_revenue'] ?? '0.000'),
            acceptanceRate: $this->rate($totalAcceptedAndPartial, $totalSubmitted),
            cancellationRate: $this->rate($totalCancelled, $totalSubmitted),
            rejectionRate: $this->rate($totalRejected, $totalSubmitted),
            ordersByStatus: $this->buildOrdersByStatusArray($ordersByStatus),
            topProducts: $topProducts,
            topSlots: $topSlots,
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
     * @return array{total_orders: int, total_revenue: string}
     */
    private function queryOrdersSummary(Shop $store, \DateTimeImmutable $dateFrom, \DateTimeImmutable $dateTo): array
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('COUNT(o.id) AS total_orders')
            ->addSelect('COALESCE(SUM(o.totalTnd), \'0.000\') AS total_revenue')
            ->from(Order::class, 'o')
            ->andWhere('o.shop = :shop')
            ->andWhere('o.createdAt >= :dateFrom')
            ->andWhere('o.createdAt < :dateTo')
            ->andWhere('o.status NOT IN (:excludedStatuses)')
            ->setParameter('shop', $store)
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->setParameter('excludedStatuses', [OrderStatus::Draft, OrderStatus::Cancelled, OrderStatus::Rejected])
            ->getQuery()
            ->getSingleResult();

        return [
            'total_orders' => (int) ($result['total_orders'] ?? 0),
            'total_revenue' => (string) ($result['total_revenue'] ?? '0.000'),
        ];
    }

    /**
     * @return array<string, array{status: string, count: int|string}>
     */
    private function queryOrdersByStatus(Shop $store, \DateTimeImmutable $dateFrom, \DateTimeImmutable $dateTo): array
    {
        $results = $this->entityManager->createQueryBuilder()
            ->select('o.status AS status')
            ->addSelect('COUNT(o.id) AS count')
            ->from(Order::class, 'o')
            ->andWhere('o.shop = :shop')
            ->andWhere('o.createdAt >= :dateFrom')
            ->andWhere('o.createdAt < :dateTo')
            ->andWhere('o.status != :draft')
            ->groupBy('o.status')
            ->setParameter('shop', $store)
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->setParameter('draft', OrderStatus::Draft)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($results as $row) {
            $status = $row['status'] instanceof OrderStatus ? $row['status']->value : (string) $row['status'];
            $indexed[$status] = ['status' => $status, 'count' => (int) $row['count']];
        }

        return $indexed;
    }

    /**
     * @return list<MerchantStatisticsTopProductOutput>
     */
    private function queryTopProducts(Shop $store, \DateTimeImmutable $dateFrom, \DateTimeImmutable $dateTo): array
    {
        $results = $this->entityManager->createQueryBuilder()
            ->select('pr.id')
            ->addSelect('pr.nameFr')
            ->addSelect('pr.nameAr')
            ->addSelect('SUM(ol.quantity) AS total_quantity')
            ->addSelect('SUM(ol.lineTotalTnd) AS total_revenue')
            ->from(OrderLine::class, 'ol')
            ->join('ol.order', 'o')
            ->join('ol.merchantProduct', 'mp')
            ->join('mp.productReference', 'pr')
            ->andWhere('o.shop = :shop')
            ->andWhere('o.createdAt >= :dateFrom')
            ->andWhere('o.createdAt < :dateTo')
            ->andWhere('o.status NOT IN (:excludedStatuses)')
            ->groupBy('pr.id')
            ->addGroupBy('pr.nameFr')
            ->addGroupBy('pr.nameAr')
            ->orderBy('total_quantity', 'DESC')
            ->setMaxResults(10)
            ->setParameter('shop', $store)
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->setParameter('excludedStatuses', [OrderStatus::Draft, OrderStatus::Cancelled, OrderStatus::Rejected])
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): MerchantStatisticsTopProductOutput => new MerchantStatisticsTopProductOutput(
                nameFr: $row['nameFr'],
                nameAr: $row['nameAr'],
                totalQuantity: (int) $row['total_quantity'],
                totalRevenueTnd: (string) $row['total_revenue'],
            ),
            $results,
        );
    }

    /**
     * @return list<MerchantStatisticsTopSlotOutput>
     */
    private function queryTopSlots(Shop $store, \DateTimeImmutable $dateFrom, \DateTimeImmutable $dateTo): array
    {
        $results = $this->entityManager->createQueryBuilder()
            ->select('ps.id')
            ->addSelect('ps.startsAt')
            ->addSelect('ps.endsAt')
            ->addSelect('COUNT(o.id) AS order_count')
            ->from(Order::class, 'o')
            ->join('o.pickupSlot', 'ps')
            ->andWhere('o.shop = :shop')
            ->andWhere('o.createdAt >= :dateFrom')
            ->andWhere('o.createdAt < :dateTo')
            ->andWhere('o.status NOT IN (:excludedStatuses)')
            ->andWhere('ps.id IS NOT NULL')
            ->groupBy('ps.id')
            ->addGroupBy('ps.startsAt')
            ->addGroupBy('ps.endsAt')
            ->orderBy('order_count', 'DESC')
            ->setMaxResults(5)
            ->setParameter('shop', $store)
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->setParameter('excludedStatuses', [OrderStatus::Draft, OrderStatus::Cancelled, OrderStatus::Rejected])
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): MerchantStatisticsTopSlotOutput => new MerchantStatisticsTopSlotOutput(
                startsAt: PickupSlotDisplayTime::toLocalAtom($row['startsAt']),
                endsAt: PickupSlotDisplayTime::toLocalAtom($row['endsAt']),
                orderCount: (int) $row['order_count'],
            ),
            $results,
        );
    }

    /**
     * @param array<string, array{status: string, count: int|string}> $ordersByStatus
     *
     * @return array<string, int>
     */
    private function buildOrdersByStatusArray(array $ordersByStatus): array
    {
        $result = [];
        foreach (OrderStatus::cases() as $status) {
            $result[$status->value] = $ordersByStatus[$status->value]['count'] ?? 0;
        }

        return $result;
    }

    private function rate(int $numerator, int $denominator): float
    {
        if (0 === $denominator) {
            return 0.0;
        }

        return round($numerator / $denominator, 3);
    }
}
