<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Kadhia;
use App\Entity\KadhiaMember;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KadhiaMember>
 */
class KadhiaMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KadhiaMember::class);
    }

    public function findOneByKadhiaAndUser(Kadhia $kadhia, User $user): ?KadhiaMember
    {
        return $this->findOneBy(['kadhia' => $kadhia, 'user' => $user]);
    }
}
