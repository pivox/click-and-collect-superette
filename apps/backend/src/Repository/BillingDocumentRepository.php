<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BillingDocument;
use App\Entity\User;
use App\Enum\BillingDocumentStatus;
use App\Enum\SubscriptionLifecycle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
    public function findPaginated(int $limit, int $offset, ?Uuid $subscriptionId = null, ?string $q = null, ?bool $outstanding = null): array
    {
        /* @var list<BillingDocument> */
        return $this->adminQuery($subscriptionId, $q, $outstanding)
            ->addOrderBy('d.issuedAt', 'DESC')
            ->addOrderBy('d.createdAt', 'DESC')
            ->addOrderBy('d.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countAll(?Uuid $subscriptionId = null, ?string $q = null, ?bool $outstanding = null): int
    {
        return (int) $this->adminQuery($subscriptionId, $q, $outstanding)
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function adminQuery(?Uuid $subscriptionId, ?string $q, ?bool $outstanding): QueryBuilder
    {
        $query = $this->createQueryBuilder('d');
        if (null !== $subscriptionId) {
            $query->andWhere('IDENTITY(d.subscription) = :subscriptionId')->setParameter('subscriptionId', $subscriptionId, 'uuid');
        }
        if (null !== $q) {
            $query->join('d.merchant', 'merchant')
                ->andWhere("LOWER(merchant.email) LIKE :search ESCAPE '!' OR LOWER(d.documentNumber) LIKE :search ESCAPE '!'")
                ->setParameter('search', '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($q)).'%');
        }
        if (null !== $outstanding) {
            $condition = 'd.status IN (:outstandingStatuses) AND d.amountDueTnd > 0';
            $query->andWhere($outstanding ? $condition : 'NOT ('.$condition.')')
                ->setParameter('outstandingStatuses', [BillingDocumentStatus::Issued, BillingDocumentStatus::Overdue]);
        }

        return $query;
    }

    public function hasOtherDueDocuments(BillingDocument $document, \DateTimeImmutable $now): bool
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('IDENTITY(d.subscription) = :subscriptionId')
            ->andWhere('d.id != :documentId')
            ->andWhere('d.status IN (:statuses)')
            ->andWhere('d.amountDueTnd > 0')
            ->andWhere('d.dueAt <= :now OR d.status = :overdue')
            ->setParameter('subscriptionId', $document->getSubscription()->getId(), 'uuid')
            ->setParameter('documentId', $document->getId(), 'uuid')
            ->setParameter('statuses', [BillingDocumentStatus::Issued, BillingDocumentStatus::Overdue])
            ->setParameter('overdue', BillingDocumentStatus::Overdue)
            ->setParameter('now', $now)
            ->getQuery()->getSingleScalarResult() > 0;
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
            ->join('d.subscription', 's')
            ->andWhere('d.status IN (:statuses)')
            ->andWhere('s.lifecycle IN (:lifecycles)')
            ->andWhere('d.dueAt IS NOT NULL')
            ->andWhere('d.amountDueTnd > :zero')
            ->setParameter('statuses', [
                BillingDocumentStatus::Issued,
                BillingDocumentStatus::Overdue,
            ])
            ->setParameter('lifecycles', [
                SubscriptionLifecycle::Active,
                SubscriptionLifecycle::PaymentDue,
                SubscriptionLifecycle::GracePeriod,
            ])
            ->setParameter('zero', '0.000')
            ->addOrderBy('d.dueAt', 'ASC')
            ->addOrderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
