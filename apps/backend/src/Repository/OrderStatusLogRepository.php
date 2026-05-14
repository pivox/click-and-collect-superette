<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderStatusLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderStatusLog>
 */
class OrderStatusLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderStatusLog::class);
    }

    /**
     * @return list<OrderStatusLog>
     */
    public function findChronologicalForOrder(Order $order): array
    {
        return $this->createQueryBuilder('log')
            ->andWhere('IDENTITY(log.order) = :orderId')
            ->setParameter('orderId', $order->getId(), 'uuid')
            ->orderBy('log.createdAt', 'ASC')
            ->addOrderBy('log.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
