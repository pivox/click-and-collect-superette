<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
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
}
