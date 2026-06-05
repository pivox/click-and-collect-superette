<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function findOneByMerchant(User $merchant): ?Subscription
    {
        return $this->findOneBy(['merchant' => $merchant]);
    }

    /**
     * @return list<Subscription>
     */
    public function findPaginated(int $limit, int $offset): array
    {
        /* @var list<Subscription> */
        return $this->createQueryBuilder('s')
            ->addOrderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

}
