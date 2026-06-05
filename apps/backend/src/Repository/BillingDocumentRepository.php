<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BillingDocument;
use App\Entity\User;
use App\Enum\BillingDocumentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<BillingDocument>
 */
class BillingDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BillingDocument::class);
    }

    /**
     * @return list<BillingDocument>
     */
    public function findPaginated(int $limit, int $offset): array
    {
        /* @var list<BillingDocument> */
        return $this->createQueryBuilder('d')
            ->addOrderBy('d.issuedAt', 'DESC')
            ->addOrderBy('d.createdAt', 'DESC')
            ->addOrderBy('d.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<BillingDocument>
     */
    public function findPaginatedForMerchant(User $merchant, int $limit, int $offset): array
    {
        /* @var list<BillingDocument> */
        return $this->createQueryBuilder('d')
            ->andWhere('IDENTITY(d.merchant) = :merchantId')
            ->setParameter('merchantId', $merchant->getId(), 'uuid')
            ->addOrderBy('d.issuedAt', 'DESC')
            ->addOrderBy('d.createdAt', 'DESC')
            ->addOrderBy('d.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countForMerchant(User $merchant): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('IDENTITY(d.merchant) = :merchantId')
            ->setParameter('merchantId', $merchant->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForMerchant(Uuid $documentId, User $merchant): ?BillingDocument
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.id = :documentId')
            ->andWhere('IDENTITY(d.merchant) = :merchantId')
            ->setParameter('documentId', $documentId, 'uuid')
            ->setParameter('merchantId', $merchant->getId(), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<BillingDocument>
     */
    public function findPaymentReminderCandidates(): array
    {
        /* @var list<BillingDocument> */
        return $this->createQueryBuilder('d')
            ->andWhere('d.status IN (:statuses)')
            ->andWhere('d.dueAt IS NOT NULL')
            ->andWhere('d.amountDueTnd > :zero')
            ->setParameter('statuses', [
                BillingDocumentStatus::Issued,
                BillingDocumentStatus::Overdue,
            ])
            ->setParameter('zero', '0.000')
            ->addOrderBy('d.dueAt', 'ASC')
            ->addOrderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
