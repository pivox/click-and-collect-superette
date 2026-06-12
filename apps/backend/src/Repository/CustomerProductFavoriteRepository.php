<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CustomerProductFavorite;
use App\Entity\MerchantProduct;
use App\Entity\Shop;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerProductFavorite>
 */
class CustomerProductFavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerProductFavorite::class);
    }

    public function findOneByCustomerAndProduct(User $customer, MerchantProduct $merchantProduct): ?CustomerProductFavorite
    {
        return $this->findOneBy([
            'customer' => $customer,
            'merchantProduct' => $merchantProduct,
        ]);
    }

    /**
     * @return list<CustomerProductFavorite>
     */
    public function findByCustomerAndShop(User $customer, Shop $shop): array
    {
        /* @var list<CustomerProductFavorite> */
        return $this->createQueryBuilder('f')
            ->join('f.merchantProduct', 'mp')
            ->addSelect('mp')
            ->andWhere('IDENTITY(f.customer) = :customerId')
            ->andWhere('IDENTITY(mp.shop) = :shopId')
            ->setParameter('customerId', $customer->getId(), 'uuid')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
