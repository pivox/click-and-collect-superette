<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdminAuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminAuditLog>
 */
class AdminAuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminAuditLog::class);
    }

    /**
     * @return list<AdminAuditLog>
     */
    public function findPaginated(
        int $limit,
        int $offset,
        ?string $action = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
    ): array {
        $qb = $this->createQueryBuilder('log')
            ->leftJoin('log.adminUser', 'u')
            ->addSelect('u')
            ->orderBy('log.createdAt', 'DESC')
            ->addOrderBy('log.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if (null !== $action) {
            $qb->andWhere('log.action = :action')->setParameter('action', $action);
        }

        if (null !== $resourceType) {
            $qb->andWhere('log.resourceType = :resourceType')->setParameter('resourceType', $resourceType);
        }

        if (null !== $resourceId) {
            $qb->andWhere('log.resourceId = :resourceId')->setParameter('resourceId', $resourceId);
        }

        /* @var list<AdminAuditLog> */
        return $qb->getQuery()->getResult();
    }

    public function countAll(
        ?string $action = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
    ): int {
        $qb = $this->createQueryBuilder('log')
            ->select('COUNT(log.id)');

        if (null !== $action) {
            $qb->andWhere('log.action = :action')->setParameter('action', $action);
        }

        if (null !== $resourceType) {
            $qb->andWhere('log.resourceType = :resourceType')->setParameter('resourceType', $resourceType);
        }

        if (null !== $resourceId) {
            $qb->andWhere('log.resourceId = :resourceId')->setParameter('resourceId', $resourceId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
