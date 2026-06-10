<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductPackItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductPackItem>
 */
final class ProductPackItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductPackItem::class);
    }
}
