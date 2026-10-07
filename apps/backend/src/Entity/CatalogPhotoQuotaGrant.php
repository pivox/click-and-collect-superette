<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CatalogPhotoQuotaGrantRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One allowance credited to a shop's shared photo-import quota
 * (CATALOG-AI-001, issue #638).
 *
 * V1 hypothesis, to validate before commercial activation: every shop gets
 * exactly one welcome grant of 10, lazily created on first use, never
 * renewed automatically — enforced by the unique (shop, source) index.
 * Admin corrections do not mutate this row or create a second one; they are
 * signed ledger entries (`CatalogPhotoQuotaOperation::AdminAdjustment`) so
 * the welcome attribution itself stays a single auditable fact.
 */
#[ORM\Entity(repositoryClass: CatalogPhotoQuotaGrantRepository::class)]
#[ORM\Table(name: 'catalog_photo_quota_grants')]
#[ORM\UniqueConstraint(name: 'UNIQ_PHOTO_QUOTA_GRANT_SHOP_SOURCE', columns: ['shop_id', 'source'])]
#[ORM\HasLifecycleCallbacks]
class CatalogPhotoQuotaGrant
{
    public const SOURCE_WELCOME = 'welcome_v1';
    public const WELCOME_ALLOWANCE = 10;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shop $shop;

    #[ORM\Column]
    private int $allowance;

    #[ORM\Column(length: 32)]
    private string $source;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $grantedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Shop $shop, int $allowance, string $source, ?User $grantedBy = null)
    {
        $this->id = Uuid::v4();
        $this->shop = $shop;
        $this->allowance = $allowance;
        $this->source = $source;
        $this->grantedBy = $grantedBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getShop(): Shop
    {
        return $this->shop;
    }

    public function getAllowance(): int
    {
        return $this->allowance;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getGrantedBy(): ?User
    {
        return $this->grantedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
