<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PlatformSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformSetting>
 */
final class PlatformSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformSetting::class);
    }

    public function findCurrent(): ?PlatformSetting
    {
        return $this->findOneBy(['singletonKey' => PlatformSetting::DEFAULT_SINGLETON_KEY]);
    }
}
