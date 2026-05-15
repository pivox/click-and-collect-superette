<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
final class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findOneByHash(string $tokenHash): ?PasswordResetToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function consumeActiveTokensForUser(User $user, \DateTimeImmutable $now): void
    {
        foreach ($this->findBy(['consumedAt' => null]) as $token) {
            if ($token->getUser()->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
                continue;
            }

            $token->consume($now);
        }
    }
}
