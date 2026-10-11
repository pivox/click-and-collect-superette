<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SubscriptionPayment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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
    public function findPaginated(int $limit, int $offset, ?Uuid $subscriptionId = null, ?Uuid $documentId = null, ?string $q = null): array
    {
        /* @var list<SubscriptionPayment> */
        return $this->adminQuery($subscriptionId, $documentId, $q)
            ->addOrderBy('p.paidAt', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countAll(?Uuid $subscriptionId = null, ?Uuid $documentId = null, ?string $q = null): int
    {
        return (int) $this->adminQuery($subscriptionId, $documentId, $q)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function adminQuery(?Uuid $subscriptionId, ?Uuid $documentId, ?string $q): QueryBuilder
    {
        $query = $this->createQueryBuilder('p');
        if (null !== $subscriptionId) {
            $query->andWhere('IDENTITY(p.subscription) = :subscriptionId')->setParameter('subscriptionId', $subscriptionId, 'uuid');
        }
        if (null !== $documentId) {
            $query->andWhere('IDENTITY(p.billingDocument) = :documentId')->setParameter('documentId', $documentId, 'uuid');
        }
        if (null !== $q) {
            $query->join('p.merchant', 'merchant')->join('p.billingDocument', 'document')
                ->andWhere("LOWER(merchant.email) LIKE :search ESCAPE '!' OR LOWER(document.documentNumber) LIKE :search ESCAPE '!' OR LOWER(p.reference) LIKE :search ESCAPE '!'")
                ->setParameter('search', '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($q)).'%');
        }

        return $query;
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
