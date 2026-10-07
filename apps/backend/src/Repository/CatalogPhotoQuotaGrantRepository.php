<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CatalogPhotoQuotaGrant;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CatalogPhotoQuotaGrant>
 */
class CatalogPhotoQuotaGrantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogPhotoQuotaGrant::class);
    }

    public function findOneWelcomeGrantByShop(Shop $shop): ?CatalogPhotoQuotaGrant
    {
        return $this->findOneBy(['shop' => $shop, 'source' => CatalogPhotoQuotaGrant::SOURCE_WELCOME]);
    }

    /**
     * Must run inside a transaction. Serializes every reservation/release
     * against the same shop so two concurrent requests with one credit left
     * never both succeed (CATALOG-AI-001 acceptance criterion).
     */
    public function findOneWelcomeGrantByShopForUpdate(Shop $shop): ?CatalogPhotoQuotaGrant
    {
        return $this->createQueryBuilder('grant')
            ->andWhere('grant.shop = :shop')
            ->andWhere('grant.source = :source')
            ->setParameter('shop', $shop->getId(), 'uuid')
            ->setParameter('source', CatalogPhotoQuotaGrant::SOURCE_WELCOME)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    public function sumAllowanceForShop(Shop $shop): int
    {
        /* @var int|string|null */
        $sum = $this->createQueryBuilder('grant')
            ->select('SUM(grant.allowance)')
            ->andWhere('grant.shop = :shop')
            ->setParameter('shop', $shop->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();

        return null === $sum ? 0 : (int) $sum;
    }
}
