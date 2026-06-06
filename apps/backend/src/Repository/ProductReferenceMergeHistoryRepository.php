<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductReferenceMergeHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductReferenceMergeHistory>
 */
class ProductReferenceMergeHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductReferenceMergeHistory::class);
    }
}
