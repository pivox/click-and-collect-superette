<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MerchantCrmStatus;
use App\Repository\MerchantCrmProfileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MerchantCrmProfileRepository::class)]
#[ORM\Table(name: 'merchant_crm_profiles')]
#[ORM\Index(name: 'IDX_MERCHANT_CRM_PROFILES_STATUS', columns: ['status'])]
#[ORM\Index(name: 'IDX_MERCHANT_CRM_PROFILES_OWNER', columns: ['commercial_owner'])]
class MerchantCrmProfile
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $merchant;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $commercialOwner = null;

    #[ORM\Column(length: 16, enumType: MerchantCrmStatus::class)]
    private MerchantCrmStatus $status = MerchantCrmStatus::Prospect;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $nextActionAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nextActionNote = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commercialNote = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastContactAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $merchant)
    {
        $this->id = Uuid::v4();
        $this->merchant = $merchant;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getMerchant(): User
    {
        return $this->merchant;
    }

    public function getCommercialOwner(): ?string
    {
        return $this->commercialOwner;
    }

    public function setCommercialOwner(?string $commercialOwner): void
    {
        $this->commercialOwner = null !== $commercialOwner ? trim($commercialOwner) : null;
        if ('' === $this->commercialOwner) {
            $this->commercialOwner = null;
        }
        $this->touch();
    }

    public function getStatus(): MerchantCrmStatus
    {
        return $this->status;
    }

    public function setStatus(MerchantCrmStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function getNextActionAt(): ?\DateTimeImmutable
    {
        return $this->nextActionAt;
    }

    public function setNextActionAt(?\DateTimeImmutable $nextActionAt): void
    {
        $this->nextActionAt = $nextActionAt;
        $this->touch();
    }

    public function getNextActionNote(): ?string
    {
        return $this->nextActionNote;
    }

    public function setNextActionNote(?string $nextActionNote): void
    {
        $this->nextActionNote = null !== $nextActionNote ? trim($nextActionNote) : null;
        if ('' === $this->nextActionNote) {
            $this->nextActionNote = null;
        }
        $this->touch();
    }

    public function getCommercialNote(): ?string
    {
        return $this->commercialNote;
    }

    public function setCommercialNote(?string $commercialNote): void
    {
        $this->commercialNote = null !== $commercialNote ? trim($commercialNote) : null;
        if ('' === $this->commercialNote) {
            $this->commercialNote = null;
        }
        $this->touch();
    }

    public function getLastContactAt(): ?\DateTimeImmutable
    {
        return $this->lastContactAt;
    }

    public function registerContactAt(\DateTimeImmutable $contactedAt): void
    {
        // A backdated contact entry must never regress the derived last contact date.
        if (null === $this->lastContactAt || $contactedAt > $this->lastContactAt) {
            $this->lastContactAt = $contactedAt;
            $this->touch();
        }
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
