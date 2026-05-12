<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Kadhia;
use App\Entity\KadhiaLine;
use App\Entity\MerchantProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KadhiaLine>
 */
class KadhiaLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KadhiaLine::class);
    }

    public function findOneByKadhiaAndProduct(Kadhia $kadhia, MerchantProduct $merchantProduct): ?KadhiaLine
    {
        return $this->findOneBy(['kadhia' => $kadhia, 'merchantProduct' => $merchantProduct]);
    }
}
