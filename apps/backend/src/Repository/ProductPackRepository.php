<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductPack;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductPack>
 */
final class ProductPackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductPack::class);
    }

    /**
     * @return ProductPack[]
     */
    public function findByShopAndActive(Shop $shop, bool $isActive = true): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.shop = :shop')
            ->andWhere('pp.isActive = :isActive')
            ->orderBy('pp.createdAt', 'DESC')
            ->setParameter('shop', $shop->getId(), 'uuid')
            ->setParameter('isActive', $isActive)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return ProductPack[]
     */
    public function findByShop(Shop $shop): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.shop = :shop')
            ->orderBy('pp.createdAt', 'DESC')
            ->setParameter('shop', $shop->getId(), 'uuid')
            ->getQuery()
            ->getResult()
        ;
    }
}
