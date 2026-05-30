<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExceptionalClosure;
use App\Entity\Shop;
use App\Service\PickupSlotDisplayTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
        $rangeStartsAt = PickupSlotDisplayTime::fromStoredLocalClock($startsAt);
        $rangeEndsAt = PickupSlotDisplayTime::fromStoredLocalClock($endsAt);

        foreach ($this->findActiveForShop($shop) as $closure) {
            if (null !== $excludeClosure && $closure->getId()->equals($excludeClosure->getId())) {
                continue;
            }

            $closureStartsAt = PickupSlotDisplayTime::fromStoredLocalClock($closure->getStartsAt());
            $closureEndsAt = PickupSlotDisplayTime::fromStoredLocalClock($closure->getEndsAt());

            if ($closureStartsAt < $rangeEndsAt && $closureEndsAt > $rangeStartsAt) {
                return true;
            }
        }

        return false;
    }
}
