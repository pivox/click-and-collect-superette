<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SubscriptionPayment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubscriptionPayment>
 */
class SubscriptionPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionPayment::class);
    }

    /** @return list<SubscriptionPayment> */
    public function findPaginated(int $limit, int $offset): array
    {
        /* @var list<SubscriptionPayment> */
        return $this->createQueryBuilder('p')
            ->addOrderBy('p.paidAt', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<SubscriptionPayment> */
    public function findPaginatedForMerchant(User $merchant, int $limit, int $offset): array
    {
        /* @var list<SubscriptionPayment> */
        return $this->createQueryBuilder('p')
            ->andWhere('IDENTITY(p.merchant) = :merchantId')
            ->setParameter('merchantId', $merchant->getId(), 'uuid')
            ->addOrderBy('p.paidAt', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countForMerchant(User $merchant): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('IDENTITY(p.merchant) = :merchantId')
            ->setParameter('merchantId', $merchant->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
