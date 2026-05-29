<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Kadhia;
use App\Entity\Order;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @return list<Order>
     */
    public function findByCustomerPaginated(User $customer, int $limit, int $offset): array
    {
        return $this->findBy(
            ['customer' => $customer],
            ['createdAt' => 'DESC'],
            $limit,
            $offset,
        );
    }

    public function countByCustomer(User $customer): int
    {
        return \count($this->findBy(['customer' => $customer]));
    }

    public function findOneByCustomerAndId(User $customer, string $orderId): ?Order
    {
        return $this->findOneBy([
            'customer' => $customer,
            'id' => $orderId,
        ]);
    }

    /**
     * @return list<Order>
     */
    public function findByShopPaginated(Shop $shop, ?string $statusFilter, int $limit, int $offset): array
    {
        $statuses = $this->parseStatusFilter($statusFilter);

        if (null === $statuses) {
            return $this->findBy(['shop' => $shop], ['createdAt' => 'DESC'], $limit, $offset);
        }

        if ([] === $statuses) {
            return [];
        }

        if (1 === \count($statuses)) {
            return $this->findBy(['shop' => $shop, 'status' => $statuses[0]], ['createdAt' => 'DESC'], $limit, $offset);
        }

        $statusValues = array_map(static fn (OrderStatus $s): string => $s->value, $statuses);

        return $this->getEntityManager()
            ->createQuery('SELECT o FROM App\Entity\Order o WHERE o.shop = :shop AND o.status IN (:statuses) ORDER BY o.createdAt DESC')
            ->setParameter('shop', $shop)
            ->setParameter('statuses', $statusValues)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getResult();
    }

    public function countByShop(Shop $shop, ?string $statusFilter): int
    {
        $statuses = $this->parseStatusFilter($statusFilter);

        if (null === $statuses) {
            return \count($this->findBy(['shop' => $shop]));
        }

        if ([] === $statuses) {
            return 0;
        }

        if (1 === \count($statuses)) {
            return \count($this->findBy(['shop' => $shop, 'status' => $statuses[0]]));
        }

        $statusValues = array_map(static fn (OrderStatus $s): string => $s->value, $statuses);

        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(o.id) FROM App\Entity\Order o WHERE o.shop = :shop AND o.status IN (:statuses)')
            ->setParameter('shop', $shop)
            ->setParameter('statuses', $statusValues)
            ->getSingleScalarResult();
    }

    /**
     * @param list<OrderStatus>|null $statuses
     *
     * @return list<Order>
     */
    public function findHistoryForShop(
        Shop $shop,
        ?array $statuses,
        ?\DateTimeImmutable $createdFrom,
        ?\DateTimeImmutable $createdTo,
        ?string $query,
        int $limit,
        int $offset,
    ): array {
        $queryBuilder = $this->createHistoryQueryBuilder($shop, $statuses, $createdFrom, $createdTo, $query)
            ->addOrderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /** @var list<Order> $orders */
        $orders = $queryBuilder->getQuery()->getResult();

        return $orders;
    }

    /**
     * @param list<OrderStatus>|null $statuses
     */
    public function countHistoryForShop(
        Shop $shop,
        ?array $statuses,
        ?\DateTimeImmutable $createdFrom,
        ?\DateTimeImmutable $createdTo,
        ?string $query,
    ): int {
        return (int) $this->createHistoryQueryBuilder($shop, $statuses, $createdFrom, $createdTo, $query)
            ->select('COUNT(DISTINCT o.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<OrderStatus>|null $statuses
     */
    private function createHistoryQueryBuilder(
        Shop $shop,
        ?array $statuses,
        ?\DateTimeImmutable $createdFrom,
        ?\DateTimeImmutable $createdTo,
        ?string $query,
    ): \Doctrine\ORM\QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('o')
            ->leftJoin('o.customer', 'customer')
            ->leftJoin('o.pickupSlot', 'pickupSlot')
            ->addSelect('customer', 'pickupSlot')
            ->andWhere('IDENTITY(o.shop) = :shopId')
            ->setParameter('shopId', $shop->getId(), 'uuid');

        if (null === $statuses) {
            $queryBuilder
                ->andWhere('o.status != :draftStatus')
                ->setParameter('draftStatus', OrderStatus::Draft);
        } elseif ([] === $statuses) {
            $queryBuilder->andWhere('1 = 0');
        } else {
            $queryBuilder
                ->andWhere('o.status IN (:statuses)')
                ->setParameter('statuses', array_map(static fn (OrderStatus $status): string => $status->value, $statuses));
        }

        if (null !== $createdFrom) {
            $queryBuilder
                ->andWhere('o.createdAt >= :createdFrom')
                ->setParameter('createdFrom', $createdFrom, Types::DATETIME_IMMUTABLE);
        }

        if (null !== $createdTo) {
            $queryBuilder
                ->andWhere('o.createdAt <= :createdTo')
                ->setParameter('createdTo', $createdTo, Types::DATETIME_IMMUTABLE);
        }

        $query = trim((string) $query);
        if ('' !== $query) {
            $escapedQuery = self::escapeLike($query);
            $queryBuilder
                ->andWhere(
                    $queryBuilder->expr()->orX(
                        'LOWER(customer.name) LIKE :query',
                        'LOWER(customer.firstName) LIKE :query',
                        'LOWER(customer.lastName) LIKE :query',
                        'customer.phone LIKE :rawQuery',
                    )
                )
                ->setParameter('query', '%'.mb_strtolower($escapedQuery).'%')
                ->setParameter('rawQuery', '%'.$escapedQuery.'%');
        }

        return $queryBuilder;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Parses a comma-separated status filter string into an array of valid OrderStatus values.
     * Returns null when no filter is provided (meaning "no filter — return all").
     * Returns an empty array when all provided values are invalid enum values.
     *
     * @return list<OrderStatus>|null
     */
    private function parseStatusFilter(?string $statusFilter): ?array
    {
        if (null === $statusFilter || '' === $statusFilter) {
            return null;
        }

        $result = [];
        foreach (explode(',', $statusFilter) as $raw) {
            $parsed = OrderStatus::tryFrom(trim($raw));
            if (null !== $parsed) {
                $result[] = $parsed;
            }
        }

        return $result;
    }

    /**
     * Returns orders in eligible pickup statuses whose slot starts within [$from, $to).
     *
     * @param list<OrderStatus> $statuses
     *
     * @return list<Order>
     */
    public function findOrdersNeedingPickupReminder(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        array $statuses,
    ): array {
        /** @var list<Order> $result */
        $result = $this->createQueryBuilder('o')
            ->innerJoin('o.pickupSlot', 'slot')
            ->leftJoin('o.shop', 'shop')
            ->leftJoin('o.customer', 'customer')
            ->addSelect('shop', 'customer')
            ->andWhere('o.status IN (:statuses)')
            ->andWhere('slot.startsAt >= :from')
            ->andWhere('slot.startsAt < :to')
            ->setParameter('statuses', array_map(static fn (OrderStatus $s) => $s->value, $statuses))
            ->setParameter('from', $from, Types::DATETIME_IMMUTABLE)
            ->setParameter('to', $to, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Returns all in-flight orders for the given shop that should be cancelled on permanent store closure.
     * Draft orders are intentionally excluded — they are incomplete and hold no reserved resources.
     *
     * @return list<Order>
     */
    public function findActiveByShop(Shop $shop): array
    {
        $statuses = [
            OrderStatus::Submitted,
            OrderStatus::Accepted,
            OrderStatus::PartiallyAccepted,
            OrderStatus::Preparing,
            OrderStatus::Ready,
            OrderStatus::PickupPending,
        ];

        /** @var list<Order> $result */
        $result = $this->createQueryBuilder('o')
            ->leftJoin('o.pickupSlot', 'slot')
            ->addSelect('slot')
            ->andWhere('IDENTITY(o.shop) = :shopId')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->setParameter('statuses', array_map(static fn (OrderStatus $s): string => $s->value, $statuses))
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Returns all non-draft orders for the export. The full result set is hydrated into memory
     * (acceptable for MVP with the 92-day cap; switch to toIterable() + batch clear for scale).
     *
     * @return list<Order>
     */
    public function findForExport(
        Shop $shop,
        ?OrderStatus $status,
        \DateTimeImmutable $createdFrom,
        \DateTimeImmutable $createdTo,
    ): array {
        /** @var list<Order> $orders */
        $orders = $this->createHistoryQueryBuilder($shop, null === $status ? null : [$status], $createdFrom, $createdTo, null)
            ->addOrderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $orders;
    }

    public function findOneByShopAndId(Shop $shop, string $orderId): ?Order
    {
        return $this->findOneBy([
            'shop' => $shop,
            'id' => $orderId,
        ]);
    }

    public function findOneByShopAndIdWithDetail(Shop $shop, string $orderId): ?Order
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.lines', 'line')
            ->leftJoin('line.merchantProduct', 'merchantProduct')
            ->leftJoin('merchantProduct.productReference', 'productReference')
            ->addSelect('line', 'merchantProduct', 'productReference')
            ->andWhere('IDENTITY(o.shop) = :shopId')
            ->andWhere('o.id = :orderId')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->setParameter('orderId', Uuid::fromString($orderId), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPartiallyAcceptedByKadhia(Kadhia $kadhia): ?Order
    {
        return $this->findOneBy([
            'kadhia' => $kadhia,
            'status' => OrderStatus::PartiallyAccepted,
        ]);
    }

    /**
     * Returns the most recent non-cancelled order linked to a Kadhia.
     * Used to implement idempotent submit: if the Kadhia is already submitted,
     * return the existing order instead of throwing an error.
     */
    public function findActiveByKadhia(Kadhia $kadhia): ?Order
    {
        return $this->createQueryBuilder('o')
            ->where('o.kadhia = :kadhia')
            ->andWhere('o.status != :cancelled')
            ->setParameter('kadhia', $kadhia->getId(), 'uuid')
            ->setParameter('cancelled', OrderStatus::Cancelled)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<string, int>
     */
    public function countByStatusForShopBetweenPickupSlotStarts(
        Shop $shop,
        \DateTimeImmutable $startsAtInclusive,
        \DateTimeImmutable $startsAtExclusive,
    ): array {
        /** @var list<array{status: OrderStatus|string, count: int|string}> $rows */
        $rows = $this->createQueryBuilder('o')
            ->select('o.status AS status, COUNT(o.id) AS count')
            ->innerJoin('o.pickupSlot', 'pickupSlot')
            ->andWhere('IDENTITY(o.shop) = :shopId')
            ->andWhere('pickupSlot.startsAt >= :startsAtInclusive')
            ->andWhere('pickupSlot.startsAt < :startsAtExclusive')
            ->groupBy('o.status')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->setParameter('startsAtInclusive', $startsAtInclusive, Types::DATETIME_IMMUTABLE)
            ->setParameter('startsAtExclusive', $startsAtExclusive, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $status = $row['status'] instanceof OrderStatus ? $row['status']->value : (string) $row['status'];
            $counts[$status] = (int) $row['count'];
        }

        return $counts;
    }

    public function countUrgentSubmittedForShopBetweenPickupSlotStarts(
        Shop $shop,
        \DateTimeImmutable $startsAtInclusive,
        \DateTimeImmutable $startsAtExclusive,
    ): int {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->innerJoin('o.pickupSlot', 'pickupSlot')
            ->andWhere('IDENTITY(o.shop) = :shopId')
            ->andWhere('o.status = :status')
            ->andWhere('pickupSlot.startsAt >= :startsAtInclusive')
            ->andWhere('pickupSlot.startsAt < :startsAtExclusive')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->setParameter('status', OrderStatus::Submitted)
            ->setParameter('startsAtInclusive', $startsAtInclusive, Types::DATETIME_IMMUTABLE)
            ->setParameter('startsAtExclusive', $startsAtExclusive, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findReadyByPickupCodeAndShop(string $code, Shop $shop): ?Order
    {
        return $this->findOneBy([
            'pickupCode' => $code,
            'shop' => $shop,
            'status' => OrderStatus::Ready,
        ]);
    }
}
