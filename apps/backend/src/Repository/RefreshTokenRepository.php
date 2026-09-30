<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
final class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findOneByHash(string $tokenHash): ?RefreshToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * @return list<RefreshToken>
     */
    public function findActiveForUser(User $user): array
    {
        // Criteria API on purpose: entity parameters in DQL are unreliable with
        // SQLite UUID BLOB columns in the test environment (pattern #2).
        return $this->findBy(['user' => $user, 'revokedAt' => null]);
    }

    /**
     * @return list<RefreshToken>
     */
    public function findActiveForFamily(Uuid $familyId): array
    {
        return $this->findBy(['familyId' => $familyId, 'revokedAt' => null]);
    }

    /**
     * Hard-deletes tokens expired or revoked before the cutoff. Returns the
     * number of deleted rows.
     */
    public function purgeObsolete(\DateTimeImmutable $cutoff): int
    {
        return (int) $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :cutoff')
            ->orWhere('rt.revokedAt IS NOT NULL AND rt.revokedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
