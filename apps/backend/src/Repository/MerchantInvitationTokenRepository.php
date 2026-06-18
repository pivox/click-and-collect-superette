<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MerchantInvitationToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantInvitationToken>
 */
final class MerchantInvitationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantInvitationToken::class);
    }

    public function findOneByHash(string $tokenHash): ?MerchantInvitationToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function revokePendingInvitationsForMerchant(User $merchant, \DateTimeImmutable $now): void
    {
        foreach ($this->findBy(['merchant' => $merchant, 'usedAt' => null, 'revokedAt' => null]) as $token) {
            $token->revoke($now);
        }
    }
}
