<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MerchantInvitationTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MerchantInvitationTokenRepository::class)]
#[ORM\Table(name: 'merchant_invitation_tokens')]
#[ORM\UniqueConstraint(name: 'UNIQ_MERCHANT_INVITATION_PENDING_MERCHANT', columns: ['merchant_id'], options: ['where' => 'used_at IS NULL AND revoked_at IS NULL'])]
#[ORM\Index(name: 'IDX_MERCHANT_INVITATION_EXPIRES_AT', columns: ['expires_at'])]
class MerchantInvitationToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $merchant;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $merchant,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
        ?User $createdBy = null,
    ) {
        $this->id = Uuid::v4();
        $this->merchant = $merchant;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getMerchant(): User
    {
        return $this->merchant;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markUsed(?\DateTimeImmutable $now = null): static
    {
        $this->usedAt = $now ?? new \DateTimeImmutable();

        return $this;
    }

    public function revoke(?\DateTimeImmutable $now = null): static
    {
        $this->revokedAt = $now ?? new \DateTimeImmutable();

        return $this;
    }

    public function isUsed(): bool
    {
        return null !== $this->usedAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt <= ($now ?? new \DateTimeImmutable());
    }
}
