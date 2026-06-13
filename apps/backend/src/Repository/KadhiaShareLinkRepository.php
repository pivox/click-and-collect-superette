<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Kadhia;
use App\Entity\KadhiaShareLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KadhiaShareLink>
 */
class KadhiaShareLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KadhiaShareLink::class);
    }

    public function findActiveForKadhia(Kadhia $kadhia, \DateTimeImmutable $now): ?KadhiaShareLink
    {
        $links = $this->createQueryBuilder('l')
            ->andWhere('l.kadhia = :kadhia')
            ->andWhere('l.active = true')
            ->setParameter('kadhia', $kadhia->getId(), 'uuid')
            ->orderBy('l.expiresAt', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($links as $link) {
            if ($link instanceof KadhiaShareLink && $link->isActiveAt($now)) {
                return $link;
            }
        }

        return null;
    }

    public function findActiveByTokenHash(string $tokenHash, \DateTimeImmutable $now): ?KadhiaShareLink
    {
        $link = $this->findOneBy(['tokenHash' => $tokenHash, 'active' => true]);

        return $link?->isActiveAt($now) ? $link : null;
    }

    /**
     * @return list<KadhiaShareLink>
     */
    public function findActiveRowsForKadhia(Kadhia $kadhia): array
    {
        /* @var list<KadhiaShareLink> */
        return $this->createQueryBuilder('l')
            ->andWhere('l.kadhia = :kadhia')
            ->andWhere('l.active = true')
            ->setParameter('kadhia', $kadhia->getId(), 'uuid')
            ->orderBy('l.expiresAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
