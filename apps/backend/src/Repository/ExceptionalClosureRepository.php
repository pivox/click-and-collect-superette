<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExceptionalClosure;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExceptionalClosure>
 */
class ExceptionalClosureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExceptionalClosure::class);
    }

    /**
     * @return list<ExceptionalClosure>
     */
    public function findActiveForShop(Shop $shop): array
    {
        return $this->createQueryBuilder('closure')
            ->andWhere('IDENTITY(closure.shop) = :shopId')
            ->andWhere('closure.isActive = true')
            ->orderBy('closure.startsAt', 'ASC')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->getQuery()
            ->getResult();
    }

    public function findActiveOneForShop(Shop $shop, string $closureId): ?ExceptionalClosure
    {
        return $this->createQueryBuilder('closure')
            ->andWhere('closure.id = :closureId')
            ->andWhere('IDENTITY(closure.shop) = :shopId')
            ->andWhere('closure.isActive = true')
            ->setParameter('closureId', $closureId, 'uuid')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasActiveOverlapForShop(
        Shop $shop,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        ?ExceptionalClosure $excludeClosure = null,
    ): bool {
        $queryBuilder = $this->createQueryBuilder('closure')
            ->andWhere('IDENTITY(closure.shop) = :shopId')
            ->andWhere('closure.isActive = true')
            ->andWhere('closure.startsAt < :endsAt')
            ->andWhere('closure.endsAt > :startsAt')
            ->setMaxResults(1)
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->setParameter('startsAt', $startsAt, Types::DATETIME_IMMUTABLE)
            ->setParameter('endsAt', $endsAt, Types::DATETIME_IMMUTABLE);

        if (null !== $excludeClosure) {
            $queryBuilder
                ->andWhere('closure.id != :excludeClosureId')
                ->setParameter('excludeClosureId', $excludeClosure->getId(), 'uuid');
        }

        return null !== $queryBuilder->getQuery()->getOneOrNullResult();
    }
}
