<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Issues and revokes opaque refresh tokens (#616).
 *
 * The raw token is random (256 bits, base64url) and returned exactly once:
 * only its sha256 hash is persisted. No flush inside this service — callers
 * own the transaction boundary.
 */
final readonly class RefreshTokenManager implements RefreshTokenRevokerInterface
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
        private EntityManagerInterface $entityManager,
        private int $refreshTokenTtl,
    ) {
    }

    /**
     * Issues a new refresh token and returns its raw value. A null $familyId
     * starts a new rotation family (fresh login); rotation passes the family
     * of the consumed token.
     */
    public function issue(
        User $user,
        ?Uuid $familyId = null,
        ?string $deviceLabel = null,
        ?string $createdByIp = null,
        ?\DateTimeImmutable $now = null,
    ): string {
        $now ??= new \DateTimeImmutable();
        $rawToken = self::generateRawToken();

        $token = new RefreshToken(
            $user,
            self::hashToken($rawToken),
            $familyId ?? Uuid::v4(),
            $now->modify(\sprintf('+%d seconds', $this->refreshTokenTtl)),
            self::normalizeDeviceLabel($deviceLabel),
            $createdByIp,
        );

        $this->entityManager->persist($token);

        return $rawToken;
    }

    public function findByRawToken(string $rawToken): ?RefreshToken
    {
        return $this->refreshTokenRepository->findOneByHash(self::hashToken($rawToken));
    }

    public function revokeAllForUser(User $user, ?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();
        $tokens = $this->refreshTokenRepository->findActiveForUser($user);
        foreach ($tokens as $token) {
            $token->revoke($now);
        }

        return \count($tokens);
    }

    /**
     * Revokes every active token of a rotation family (theft detection on
     * replay of a consumed token).
     */
    public function revokeFamily(Uuid $familyId, ?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();
        $tokens = $this->refreshTokenRepository->findActiveForFamily($familyId);
        foreach ($tokens as $token) {
            $token->revoke($now);
        }

        return \count($tokens);
    }

    /**
     * 256 bits of entropy, base64url without padding (43 chars).
     */
    public static function generateRawToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function normalizeDeviceLabel(?string $deviceLabel): ?string
    {
        if (null === $deviceLabel) {
            return null;
        }

        $deviceLabel = trim($deviceLabel);
        if ('' === $deviceLabel) {
            return null;
        }

        return mb_substr($deviceLabel, 0, 120);
    }
}
