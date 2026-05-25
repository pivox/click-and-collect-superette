<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MerchantCategory;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantCategory>
 */
class MerchantCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantCategory::class);
    }

    /**
     * @return list<MerchantCategory>
     */
    public function findForShop(Shop $shop): array
    {
        return $this->findBy(
            ['shop' => $shop],
            ['sortOrder' => 'ASC', 'nameFr' => 'ASC'],
        );
    }

    public function findOneForShopAndSlug(Shop $shop, string $slug): ?MerchantCategory
    {
        return $this->findOneBy([
            'shop' => $shop,
            'slug' => $slug,
        ]);
    }
}
