<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MerchantOrganizationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Commercial merchant identity (MERCHANT-TEAM-001).
 *
 * Owns the shops, subscription, CRM profile and billing of a merchant, while
 * login accounts attach to it through MerchantMembership. During the
 * expand/backfill phase, Shop.owner remains authoritative for access control.
 */
#[ORM\Entity(repositoryClass: MerchantOrganizationRepository::class)]
#[ORM\Table(name: 'merchant_organizations')]
#[ORM\HasLifecycleCallbacks]
class MerchantOrganization
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column]
    private bool $active = true;

    // Nullable at the database level so an anomaly (deleted primary account)
    // is detectable by the audit command instead of breaking reads.
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Assert\NotNull]
    private ?User $primaryAccount = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getPrimaryAccount(): ?User
    {
        return $this->primaryAccount;
    }

    public function setPrimaryAccount(?User $primaryAccount): self
    {
        $this->primaryAccount = $primaryAccount;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeImmutable $archivedAt): self
    {
        $this->archivedAt = $archivedAt;

        return $this;
    }
}
