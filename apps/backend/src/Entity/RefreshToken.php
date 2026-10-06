<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Opaque mobile refresh token (#616).
 *
 * The raw token is random, returned once at issuance and never stored: only
 * its sha256 hash is persisted (same model as PasswordResetToken). Tokens are
 * grouped in a rotation family (one family per login/device): presenting a
 * token already consumed by rotation reveals theft and revokes the family.
 */
#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\Index(columns: ['user_id'], name: 'IDX_REFRESH_TOKENS_USER')]
#[ORM\Index(columns: ['family_id'], name: 'IDX_REFRESH_TOKENS_FAMILY')]
#[ORM\Index(columns: ['expires_at'], name: 'IDX_REFRESH_TOKENS_EXPIRES_AT')]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    /** Password-hash snapshot; legacy tokens without one must fail closed. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $credentialHash = null;

    /** Rotation family: shared by every token descending from the same login. */
    #[ORM\Column(type: 'uuid')]
    private Uuid $familyId;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Set when the token is consumed by a successful rotation. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    /** Free label provided by the app ("Pixel 7 de Haythem") — never a hardware identifier. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $deviceLabel = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $createdByIp = null;

    public function __construct(
        User $user,
        string $tokenHash,
        Uuid $familyId,
        \DateTimeImmutable $expiresAt,
        ?string $deviceLabel = null,
        ?string $createdByIp = null,
    ) {
        $this->id = Uuid::v4();
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->credentialHash = hash('sha256', $user->getPassword());
        $this->familyId = $familyId;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
        $this->deviceLabel = $deviceLabel;
        $this->createdByIp = $createdByIp;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function hasCurrentCredentials(): bool
    {
        return null !== $this->credentialHash
            && hash_equals($this->credentialHash, hash('sha256', $this->user->getPassword()));
    }

    public function getFamilyId(): Uuid
    {
        return $this->familyId;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getDeviceLabel(): ?string
    {
        return $this->deviceLabel;
    }

    public function getCreatedByIp(): ?string
    {
        return $this->createdByIp;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt <= ($now ?? new \DateTimeImmutable());
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    /**
     * Administrative revocation (logout, password reset, suspension, deletion).
     */
    public function revoke(?\DateTimeImmutable $now = null): static
    {
        $this->revokedAt ??= $now ?? new \DateTimeImmutable();

        return $this;
    }

    /**
     * Single-use rotation: the token is spent and replaced by a new one.
     */
    public function consumeForRotation(?\DateTimeImmutable $now = null): static
    {
        $now ??= new \DateTimeImmutable();
        $this->lastUsedAt = $now;
        $this->revokedAt ??= $now;

        return $this;
    }

    /**
     * True when the token was already spent by a rotation: presenting it again
     * is a replay (theft detection) — unlike a token revoked administratively.
     */
    public function wasConsumedByRotation(): bool
    {
        return null !== $this->revokedAt && null !== $this->lastUsedAt;
    }
}
