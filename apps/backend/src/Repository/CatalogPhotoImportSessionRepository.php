<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CatalogPhotoImportSession;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<CatalogPhotoImportSession>
 */
class CatalogPhotoImportSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogPhotoImportSession::class);
    }

    public function findOneForShop(Shop $shop, string $sessionId): ?CatalogPhotoImportSession
    {
        if (!Uuid::isValid($sessionId)) {
            return null;
        }

        return $this->createQueryBuilder('session')
            ->andWhere('session.id = :sessionId')
            ->andWhere('session.shop = :shop')
            ->setParameter('sessionId', $sessionId, 'uuid')
            ->setParameter('shop', $shop->getId(), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<CatalogPhotoImportSession>
     */
    public function findForShop(Shop $shop, int $limit, int $offset): array
    {
        /* @var list<CatalogPhotoImportSession> */
        return $this->createQueryBuilder('session')
            ->andWhere('session.shop = :shop')
            ->setParameter('shop', $shop->getId(), 'uuid')
            ->orderBy('session.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countForShop(Shop $shop): int
    {
        /* @var int|string */
        $count = $this->createQueryBuilder('session')
            ->select('COUNT(session.id)')
            ->andWhere('session.shop = :shop')
            ->setParameter('shop', $shop->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
