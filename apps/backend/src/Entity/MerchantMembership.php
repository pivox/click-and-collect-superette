<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MerchantMembershipStatus;
use App\Repository\MerchantMembershipRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Link between a login account and a merchant organization (MERCHANT-TEAM-001).
 *
 * V1 invariant: a merchant User holds at most one active membership. It is
 * enforced by a partial unique index on (user_id) WHERE status = 'active'
 * (PostgreSQL and SQLite) plus transactional checks in the writers.
 */
#[ORM\Entity(repositoryClass: MerchantMembershipRepository::class)]
#[ORM\Table(name: 'merchant_memberships')]
#[ORM\UniqueConstraint(name: 'uniq_merchant_membership_org_user', columns: ['merchant_organization_id', 'user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_merchant_membership_active_user', columns: ['user_id'], options: ['where' => "(status = 'active')"])]
#[ORM\Index(name: 'idx_merchant_membership_user_status', columns: ['user_id', 'status'])]
#[ORM\Index(name: 'idx_merchant_membership_org_status', columns: ['merchant_organization_id', 'status'])]
#[ORM\HasLifecycleCallbacks]
class MerchantMembership
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: MerchantOrganization::class)]
    #[ORM\JoinColumn(name: 'merchant_organization_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?MerchantOrganization $organization = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\Column(length: 16, enumType: MerchantMembershipStatus::class)]
    private MerchantMembershipStatus $status = MerchantMembershipStatus::Invited;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $invitedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $invitedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $revokedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

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

    public function getOrganization(): ?MerchantOrganization
    {
        return $this->organization;
    }

    public function setOrganization(MerchantOrganization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getStatus(): MerchantMembershipStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return MerchantMembershipStatus::Active === $this->status;
    }

    public function markInvited(User $invitedBy, ?\DateTimeImmutable $at = null): self
    {
        $this->status = MerchantMembershipStatus::Invited;
        $this->invitedBy = $invitedBy;
        $this->invitedAt = $at ?? new \DateTimeImmutable();

        return $this;
    }

    public function activate(?\DateTimeImmutable $at = null): self
    {
        $this->status = MerchantMembershipStatus::Active;
        $this->acceptedAt = $at ?? new \DateTimeImmutable();
        $this->revokedAt = null;
        $this->revokedBy = null;

        return $this;
    }

    public function revoke(?User $revokedBy, ?\DateTimeImmutable $at = null): self
    {
        $this->status = MerchantMembershipStatus::Revoked;
        $this->revokedAt = $at ?? new \DateTimeImmutable();
        $this->revokedBy = $revokedBy;

        return $this;
    }

    public function getInvitedBy(): ?User
    {
        return $this->invitedBy;
    }

    public function getInvitedAt(): ?\DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getRevokedBy(): ?User
    {
        return $this->revokedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
