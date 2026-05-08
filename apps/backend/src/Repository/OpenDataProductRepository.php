<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OpenDataProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OpenDataProduct>
 */
class OpenDataProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OpenDataProduct::class);
    }

    public function findOneByBarcode(string $barcode): ?OpenDataProduct
    {
        return $this->findOneBy(['barcode' => $barcode]);
    }
}
