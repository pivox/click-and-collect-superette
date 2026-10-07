<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CatalogPhotoQuotaEntry;
use App\Entity\Shop;
use App\Enum\CatalogPhotoQuotaOperation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CatalogPhotoQuotaEntry>
 */
class CatalogPhotoQuotaEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogPhotoQuotaEntry::class);
    }

    public function findOneByIdempotencyKey(string $idempotencyKey): ?CatalogPhotoQuotaEntry
    {
        return $this->findOneBy(['idempotencyKey' => $idempotencyKey]);
    }

    /**
     * Sum of every ledger delta for a shop's grants (reservation=-1,
     * release=+1, consumption=0, admin_adjustment=+/-N).
     */
    public function sumQuantityForShop(Shop $shop): int
    {
        /* @var int|string|null */
        $sum = $this->createQueryBuilder('entry')
            ->select('SUM(entry.quantity)')
            ->innerJoin('entry.grant', 'grant')
            ->andWhere('grant.shop = :shop')
            ->setParameter('shop', $shop->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();

        return null === $sum ? 0 : (int) $sum;
    }

    /**
     * Count of ledger lines for one operation — every reservation/release/
     * consumption is exactly one unit, so "outstanding reserved" is simply
     * reservations minus releases minus consumptions (never queried against
     * image status, which would double the source of truth).
     */
    public function countByOperationForShop(Shop $shop, CatalogPhotoQuotaOperation $operation): int
    {
        /* @var int|string|null */
        $count = $this->createQueryBuilder('entry')
            ->select('COUNT(entry.id)')
            ->innerJoin('entry.grant', 'grant')
            ->andWhere('grant.shop = :shop')
            ->andWhere('entry.operation = :operation')
            ->setParameter('shop', $shop->getId(), 'uuid')
            ->setParameter('operation', $operation)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $count ? 0 : (int) $count;
    }
}
