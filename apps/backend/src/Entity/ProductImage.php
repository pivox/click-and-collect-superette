<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProductImageSource;
use App\Enum\ProductImageStatus;
use App\Repository\ProductImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Stored product image (original + responsive WebP variants + JPEG fallback).
 *
 * The official picture belongs to the shared ProductReference. AI enrichment and
 * merchant contributions may attach a candidate image to a ProductReference or a
 * ProductReferenceProposal, but never auto-promote it to the official one
 * (see ProductImageSource / ProductImageStatus).
 *
 * Evolution note: merchant_product_id / product_candidate_id columns can be added
 * later when those flows ship — kept out of the MVP table to avoid orphan columns.
 */
#[ORM\Entity(repositoryClass: ProductImageRepository::class)]
#[ORM\Table(name: 'product_images')]
#[ORM\Index(name: 'IDX_PRODUCT_IMAGES_REFERENCE', columns: ['product_reference_id'])]
#[ORM\Index(name: 'IDX_PRODUCT_IMAGES_PROPOSAL', columns: ['product_reference_proposal_id'])]
#[ORM\Index(name: 'IDX_PRODUCT_IMAGES_STATUS', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class ProductImage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: ProductReference::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?ProductReference $productReference = null;

    #[ORM\ManyToOne(targetEntity: ProductReferenceProposal::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?ProductReferenceProposal $productReferenceProposal = null;

    /** Relative public path to the preserved original upload. */
    #[ORM\Column(length: 1024)]
    private string $originalPath;

    /** Normalized MIME type of the original (image/jpeg, image/png, image/webp). */
    #[ORM\Column(length: 64)]
    private string $mimeType;

    #[ORM\Column(type: 'integer')]
    private int $width;

    #[ORM\Column(type: 'integer')]
    private int $height;

    /**
     * Map of generated variants: { "200": path, "400": path, "800": path,
     * "1200": path, "fallback_jpeg": path }. Paths are relative public web paths.
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: 'json')]
    private array $variants = [];

    #[ORM\Column(length: 32, enumType: ProductImageSource::class)]
    private ProductImageSource $source;

    #[ORM\Column(length: 32, enumType: ProductImageStatus::class)]
    private ProductImageStatus $status;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $altText = null;

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

    public function getProductReference(): ?ProductReference
    {
        return $this->productReference;
    }

    public function setProductReference(?ProductReference $productReference): static
    {
        $this->productReference = $productReference;

        return $this;
    }

    public function getProductReferenceProposal(): ?ProductReferenceProposal
    {
        return $this->productReferenceProposal;
    }

    public function setProductReferenceProposal(?ProductReferenceProposal $productReferenceProposal): static
    {
        $this->productReferenceProposal = $productReferenceProposal;

        return $this;
    }

    public function getOriginalPath(): string
    {
        return $this->originalPath;
    }

    public function setOriginalPath(string $originalPath): static
    {
        $this->originalPath = $originalPath;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    /**
     * @param array<string, string> $variants
     */
    public function setVariants(array $variants): static
    {
        $this->variants = $variants;

        return $this;
    }

    public function getVariant(string $key): ?string
    {
        return $this->variants[$key] ?? null;
    }

    public function getSource(): ProductImageSource
    {
        return $this->source;
    }

    public function setSource(ProductImageSource $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getStatus(): ProductImageStatus
    {
        return $this->status;
    }

    public function setStatus(ProductImageStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): static
    {
        $this->altText = $altText;

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

    /**
     * Directory key under the uploads root (the image UUID). All files for this
     * image (original + variants) live under this single directory.
     */
    public function getStorageKey(): string
    {
        return $this->id->toRfc4122();
    }
}
