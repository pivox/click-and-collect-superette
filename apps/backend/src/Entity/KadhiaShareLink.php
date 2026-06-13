<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\KadhiaShareLinkRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: KadhiaShareLinkRepository::class)]
#[ORM\Table(name: 'kadhia_share_links')]
#[ORM\UniqueConstraint(name: 'UNIQ_KADHIA_SHARE_LINKS_TOKEN_HASH', columns: ['token_hash'])]
#[ORM\Index(name: 'IDX_KADHIA_SHARE_LINKS_KADHIA', columns: ['kadhia_id'])]
#[ORM\Index(name: 'IDX_KADHIA_SHARE_LINKS_EXPIRES_AT', columns: ['expires_at'])]
class KadhiaShareLink
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Kadhia::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Kadhia $kadhia;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $createdBy;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getKadhia(): Kadhia
    {
        return $this->kadhia;
    }

    public function setKadhia(Kadhia $kadhia): static
    {
        $this->kadhia = $kadhia;

        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function isActiveAt(\DateTimeImmutable $now): bool
    {
        return $this->active && $this->expiresAt > $now;
    }
}
