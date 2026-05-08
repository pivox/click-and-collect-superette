<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantProduct>
 */
class MerchantProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantProduct::class);
    }

    /**
     * @return list<MerchantProduct>
     */
    public function findCatalogForShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop]);
    }

    public function findOneForShopAndProductReference(Shop $shop, ProductReference $productReference): ?MerchantProduct
    {
        return $this->findOneBy([
            'shop' => $shop,
            'productReference' => $productReference,
        ]);
    }
}
