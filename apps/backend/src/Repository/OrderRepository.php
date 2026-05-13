<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Kadhia;
use App\Entity\Order;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    public function findPartiallyAcceptedByKadhia(Kadhia $kadhia): ?Order
    {
        return $this->findOneBy([
            'kadhia' => $kadhia,
            'status' => OrderStatus::PartiallyAccepted,
        ]);
    }
}
