<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductImportRaw;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductImportRaw>
 */
class ProductImportRawRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductImportRaw::class);
    }
}
