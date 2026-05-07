<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PlatformTheme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformTheme>
 */
class PlatformThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformTheme::class);
    }

    public function findDefault(): ?PlatformTheme
    {
        return $this->find(PlatformTheme::DEFAULT_ID);
    }
}
