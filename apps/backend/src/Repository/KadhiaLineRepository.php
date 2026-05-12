<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\KadhiaLine;
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
}
