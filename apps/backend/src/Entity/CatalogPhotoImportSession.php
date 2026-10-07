<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CatalogPhotoImportSessionStatus;
use App\Repository\CatalogPhotoImportSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A merchant's photo-import session (CATALOG-AI-001, issue #638).
 *
 * `createdByUser` is kept for audit only — every account active on the shop's
 * organization can read/edit/cancel a draft started by a teammate
 * (MerchantShopAccessChecker enforces that, not entity ownership).
 *
 * This issue only reaches Draft and Cancelled. Queued onward is driven by the
 * async dispatch built in CATALOG-AI-004 (#641).
 */
#[ORM\Entity(repositoryClass: CatalogPhotoImportSessionRepository::class)]
#[ORM\Table(name: 'catalog_photo_import_sessions')]
#[ORM\HasLifecycleCallbacks]
class CatalogPhotoImportSession
{
    public const array ALLOWED_MODES = ['receipt', 'shelf', 'cash_register_export', 'paper_list'];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shop $shop;

    // Always set at creation (never null in PHP) — the DB column stays
    // nullable/SET NULL purely as a defensive audit safety net; this app
    // never hard-deletes a User (customers are soft-deleted via deletedAt).
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_user_id', nullable: true, onDelete: 'SET NULL')]
    private User $createdByUser;

    #[ORM\Column(length: 32, enumType: CatalogPhotoImportSessionStatus::class)]
    private CatalogPhotoImportSessionStatus $status = CatalogPhotoImportSessionStatus::Draft;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $mode = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $campaignId = null;

    #[ORM\Column]
    private int $configurationVersion = 1;

    /** Optimistic lock: a stale PATCH (concurrent teammate edit) fails instead of silently overwriting. */
    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    /** @var Collection<int, CatalogPhotoImportImage> */
    #[ORM\OneToMany(targetEntity: CatalogPhotoImportImage::class, mappedBy: 'session')]
    private Collection $images;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    public function __construct(Shop $shop, User $createdByUser, ?string $mode)
    {
        $this->id = Uuid::v4();
        $this->shop = $shop;
        $this->createdByUser = $createdByUser;
        $this->mode = $mode;
        $this->images = new ArrayCollection();
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

    public function getShop(): Shop
    {
        return $this->shop;
    }

    public function getCreatedByUser(): User
    {
        return $this->createdByUser;
    }

    public function getStatus(): CatalogPhotoImportSessionStatus
    {
        return $this->status;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(?string $mode): void
    {
        $this->mode = $mode;
    }

    public function getCampaignId(): ?string
    {
        return $this->campaignId;
    }

    public function setCampaignId(?string $campaignId): void
    {
        $this->campaignId = $campaignId;
    }

    public function getConfigurationVersion(): int
    {
        return $this->configurationVersion;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return Collection<int, CatalogPhotoImportImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function getActiveImageCount(): int
    {
        return $this->images->filter(static fn (CatalogPhotoImportImage $image): bool => $image->isActive())->count();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function isDraft(): bool
    {
        return CatalogPhotoImportSessionStatus::Draft === $this->status;
    }

    public function cancel(): void
    {
        $this->status = CatalogPhotoImportSessionStatus::Cancelled;
        $this->cancelledAt = new \DateTimeImmutable();
    }
}
