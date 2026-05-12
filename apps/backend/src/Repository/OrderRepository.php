<?php

declare(strict_types=1);

namespace App\Repository;

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
    public function findByShopPaginated(Shop $shop, ?string $status, int $limit, int $offset): array
    {
        $criteria = ['shop' => $shop];
        if (null !== $status) {
            $parsed = OrderStatus::tryFrom($status);
            if (null !== $parsed) {
                $criteria['status'] = $parsed;
            }
        }

        return $this->findBy($criteria, ['createdAt' => 'DESC'], $limit, $offset);
    }

    public function countByShop(Shop $shop, ?string $status): int
    {
        $criteria = ['shop' => $shop];
        if (null !== $status) {
            $parsed = OrderStatus::tryFrom($status);
            if (null !== $parsed) {
                $criteria['status'] = $parsed;
            }
        }

        return \count($this->findBy($criteria));
    }

    public function findOneByShopAndId(Shop $shop, string $orderId): ?Order
    {
        return $this->findOneBy([
            'shop' => $shop,
            'id' => $orderId,
        ]);
    }
}
