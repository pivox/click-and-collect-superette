<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductImportRawRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProductImportRawRepository::class)]
#[ORM\Table(name: 'product_import_raw')]
#[ORM\UniqueConstraint(name: 'UNIQ_PRODUCT_IMPORT_RAW_SOURCE_URL', columns: ['source_name', 'source_url'])]
#[ORM\Index(name: 'IDX_PRODUCT_IMPORT_RAW_SOURCE', columns: ['source_name'])]
#[ORM\Index(name: 'IDX_PRODUCT_IMPORT_RAW_PRODUCTION_USABLE', columns: ['production_usable'])]
#[ORM\HasLifecycleCallbacks]
class ProductImportRaw
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'source_name', length: 100)]
    private string $sourceName;

    #[ORM\Column(name: 'source_url', type: 'text', nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(name: 'raw_title', type: 'text')]
    private string $rawTitle;

    #[ORM\Column(name: 'raw_brand', length: 120, nullable: true)]
    private ?string $rawBrand = null;

    #[ORM\Column(name: 'raw_quantity', length: 80, nullable: true)]
    private ?string $rawQuantity = null;

    #[ORM\Column(name: 'raw_category', length: 120, nullable: true)]
    private ?string $rawCategory = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'raw_payload', type: 'json', nullable: true)]
    private ?array $rawPayload = null;

    #[ORM\Column(name: 'production_usable')]
    private bool $productionUsable = false;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
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

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    public function setSourceName(string $sourceName): static
    {
        $this->sourceName = $sourceName;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getRawTitle(): string
    {
        return $this->rawTitle;
    }

    public function setRawTitle(string $rawTitle): static
    {
        $this->rawTitle = $rawTitle;

        return $this;
    }

    public function getRawBrand(): ?string
    {
        return $this->rawBrand;
    }

    public function setRawBrand(?string $rawBrand): static
    {
        $this->rawBrand = $rawBrand;

        return $this;
    }

    public function getRawQuantity(): ?string
    {
        return $this->rawQuantity;
    }

    public function setRawQuantity(?string $rawQuantity): static
    {
        $this->rawQuantity = $rawQuantity;

        return $this;
    }

    public function getRawCategory(): ?string
    {
        return $this->rawCategory;
    }

    public function setRawCategory(?string $rawCategory): static
    {
        $this->rawCategory = $rawCategory;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getRawPayload(): ?array
    {
        return $this->rawPayload;
    }

    /** @param array<string, mixed>|null $rawPayload */
    public function setRawPayload(?array $rawPayload): static
    {
        $this->rawPayload = $rawPayload;

        return $this;
    }

    public function isProductionUsable(): bool
    {
        return $this->productionUsable;
    }

    public function setProductionUsable(bool $productionUsable): static
    {
        $this->productionUsable = $productionUsable;

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
}
