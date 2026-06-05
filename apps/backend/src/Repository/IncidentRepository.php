<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Incident;
use App\Entity\User;
use App\Enum\IncidentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Incident>
 */
class IncidentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Incident::class);
    }

    /**
     * @return list<Incident>
     */
    public function findAdminPaginated(int $limit, int $offset, ?User $merchant, ?User $customer, ?IncidentStatus $status): array
    {
        $queryBuilder = $this->createQueryBuilder('incident')
            ->orderBy('incident.createdAt', 'DESC')
            ->addOrderBy('incident.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        $this->applyAdminFilters($queryBuilder, $merchant, $customer, $status);

        /** @var list<Incident> $incidents */
        $incidents = $queryBuilder->getQuery()->getResult();

        return $incidents;
    }

    public function countAdmin(?User $merchant, ?User $customer, ?IncidentStatus $status): int
    {
        $queryBuilder = $this->createQueryBuilder('incident')
            ->select('COUNT(incident.id)');
        $this->applyAdminFilters($queryBuilder, $merchant, $customer, $status);

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    private function applyAdminFilters(QueryBuilder $queryBuilder, ?User $merchant, ?User $customer, ?IncidentStatus $status): void
    {
        if (null !== $merchant) {
            $queryBuilder
                ->andWhere('IDENTITY(incident.merchant) = :merchantId')
                ->setParameter('merchantId', $merchant->getId(), 'uuid');
        }
        if (null !== $customer) {
            $queryBuilder
                ->andWhere('IDENTITY(incident.customer) = :customerId')
                ->setParameter('customerId', $customer->getId(), 'uuid');
        }
        if (null !== $status) {
            $queryBuilder
                ->andWhere('incident.status = :status')
                ->setParameter('status', $status->value);
        }
    }
}
