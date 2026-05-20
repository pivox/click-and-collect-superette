<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shop;
use App\Entity\ShopTheme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopTheme>
 */
class ShopThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopTheme::class);
    }

    public function existsForShop(Shop $shop): bool
    {
        return null !== $this->createQueryBuilder('t')
            ->select('1')
            ->andWhere('IDENTITY(t.shop) = :shopId')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
