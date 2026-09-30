<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\RefreshToken;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RefreshTokenTest extends TestCase
{
    public function testNewTokenIsActive(): void
    {
        $token = $this->makeToken();

        self::assertFalse($token->isRevoked());
        self::assertFalse($token->isExpired());
        self::assertFalse($token->wasConsumedByRotation());
        self::assertNull($token->getLastUsedAt());
        self::assertNull($token->getRevokedAt());
    }

    public function testIsExpiredUsesProvidedClock(): void
    {
        $expiresAt = new \DateTimeImmutable('+30 days');
        $token = $this->makeToken(expiresAt: $expiresAt);

        self::assertFalse($token->isExpired($expiresAt->modify('-1 second')));
        self::assertTrue($token->isExpired($expiresAt));
        self::assertTrue($token->isExpired($expiresAt->modify('+1 second')));
    }

    public function testAdministrativeRevocationIsNotARotationReplay(): void
    {
        $token = $this->makeToken();
        $now = new \DateTimeImmutable();

        $token->revoke($now);

        self::assertTrue($token->isRevoked());
        self::assertSame($now, $token->getRevokedAt());
        // Logout/admin revocation must yield a generic 401, never the
        // family-revoking REUSED path.
        self::assertFalse($token->wasConsumedByRotation());
    }

    public function testRevokeIsIdempotentAndKeepsFirstTimestamp(): void
    {
        $token = $this->makeToken();
        $first = new \DateTimeImmutable('-1 hour');

        $token->revoke($first);
        $token->revoke(new \DateTimeImmutable());

        self::assertSame($first, $token->getRevokedAt());
    }

    public function testConsumeForRotationMarksTokenAsSpent(): void
    {
        $token = $this->makeToken();
        $now = new \DateTimeImmutable();

        $token->consumeForRotation($now);

        self::assertTrue($token->isRevoked());
        self::assertSame($now, $token->getLastUsedAt());
        self::assertSame($now, $token->getRevokedAt());
        self::assertTrue($token->wasConsumedByRotation());
    }

    public function testFamilyIsSharedAcrossRotatedTokens(): void
    {
        $familyId = Uuid::v4();
        $first = $this->makeToken(familyId: $familyId);
        $successor = $this->makeToken(familyId: $first->getFamilyId());

        self::assertTrue($first->getFamilyId()->equals($successor->getFamilyId()));
        self::assertFalse($first->getId()->equals($successor->getId()));
    }

    private function makeToken(?Uuid $familyId = null, ?\DateTimeImmutable $expiresAt = null): RefreshToken
    {
        $user = (new User())
            ->setEmail('unit.refresh@example.test')
            ->setPassword('irrelevant')
            ->setName('Unit User');

        return new RefreshToken(
            $user,
            hash('sha256', 'raw-token-'.Uuid::v4()->toRfc4122()),
            $familyId ?? Uuid::v4(),
            $expiresAt ?? new \DateTimeImmutable('+30 days'),
        );
    }
}
