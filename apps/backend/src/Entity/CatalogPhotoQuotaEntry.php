<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CatalogPhotoQuotaOperation;
use App\Repository\CatalogPhotoQuotaEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Append-only journal line for the merchant photo quota (CATALOG-AI-001,
 * issue #638). The available balance is always derived by summing entries —
 * never stored denormalized — so every debit/credit stays auditable.
 *
 * `idempotencyKey` is unique: a retried request (duplicate mutation, Messenger
 * redelivery once #641 lands) replays into the same row instead of a second
 * debit. `importImage` is null only for admin adjustments, which are not tied
 * to a specific photo.
 */
#[ORM\Entity(repositoryClass: CatalogPhotoQuotaEntryRepository::class)]
#[ORM\Table(name: 'catalog_photo_quota_entries')]
#[ORM\UniqueConstraint(name: 'UNIQ_PHOTO_QUOTA_ENTRY_IDEMPOTENCY', columns: ['idempotency_key'])]
class CatalogPhotoQuotaEntry
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: CatalogPhotoQuotaGrant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CatalogPhotoQuotaGrant $grant;

    #[ORM\ManyToOne(targetEntity: CatalogPhotoImportImage::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CatalogPhotoImportImage $importImage = null;

    #[ORM\Column(length: 32, enumType: CatalogPhotoQuotaOperation::class)]
    private CatalogPhotoQuotaOperation $operation;

    /** Signed delta applied to the available balance (reservation=-1, release=+1, consumption=0). */
    #[ORM\Column]
    private int $quantity;

    #[ORM\Column(length: 128, unique: true)]
    private string $idempotencyKey;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        CatalogPhotoQuotaGrant $grant,
        CatalogPhotoQuotaOperation $operation,
        int $quantity,
        string $idempotencyKey,
        ?CatalogPhotoImportImage $importImage = null,
        ?string $reason = null,
    ) {
        $this->id = Uuid::v4();
        $this->grant = $grant;
        $this->operation = $operation;
        $this->quantity = $quantity;
        $this->idempotencyKey = $idempotencyKey;
        $this->importImage = $importImage;
        $this->reason = $reason;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getGrant(): CatalogPhotoQuotaGrant
    {
        return $this->grant;
    }

    public function getImportImage(): ?CatalogPhotoImportImage
    {
        return $this->importImage;
    }

    public function getOperation(): CatalogPhotoQuotaOperation
    {
        return $this->operation;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
