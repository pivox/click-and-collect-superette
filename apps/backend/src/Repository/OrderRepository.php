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
}
