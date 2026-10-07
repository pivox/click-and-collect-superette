<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CatalogPhotoImportImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One registered source photo inside an import session (CATALOG-AI-001,
 * issue #638) — one register = at most one quota reservation.
 *
 * Real file storage (`private_storage_key`, `preprocessing_version`) is
 * CATALOG-AI-002 (#639) and will be added by its own migration then. This
 * issue only validates the upload on the fly (MIME + size, mirroring the
 * historical preview endpoint) to compute `sourceHash`, the dedup/idempotency
 * key, then discards the bytes.
 *
 * Dedup is application-level, not a DB unique constraint: replaying the same
 * file while it is still active hits the existing row (no second
 * reservation), but a hash that was removed and re-uploaded later is a new
 * row with a new reservation — removal never blocks re-adding the same
 * photo on purpose.
 */
#[ORM\Entity(repositoryClass: CatalogPhotoImportImageRepository::class)]
#[ORM\Table(name: 'catalog_photo_import_images')]
#[ORM\Index(name: 'IDX_PHOTO_IMPORT_IMAGE_SESSION_HASH', columns: ['session_id', 'source_hash'])]
class CatalogPhotoImportImage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: CatalogPhotoImportSession::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CatalogPhotoImportSession $session;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shop $shop;

    #[ORM\Column(length: 64)]
    private string $sourceHash;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $removedAt = null;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REMOVED = 'removed';

    public function __construct(CatalogPhotoImportSession $session, Shop $shop, string $sourceHash)
    {
        $this->id = Uuid::v4();
        $this->session = $session;
        $this->shop = $shop;
        $this->sourceHash = $sourceHash;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSession(): CatalogPhotoImportSession
    {
        return $this->session;
    }

    public function getShop(): Shop
    {
        return $this->shop;
    }

    public function getSourceHash(): string
    {
        return $this->sourceHash;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return self::STATUS_ACTIVE === $this->status;
    }

    public function remove(): void
    {
        $this->status = self::STATUS_REMOVED;
        $this->removedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRemovedAt(): ?\DateTimeImmutable
    {
        return $this->removedAt;
    }
}
