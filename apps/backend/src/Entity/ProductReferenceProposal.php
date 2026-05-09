<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProductReferenceProposalStatus;
use App\Enum\ProductUnit;
use App\Repository\ProductReferenceProposalRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductReferenceProposalRepository::class)]
#[ORM\Table(name: 'product_reference_proposals')]
#[ORM\HasLifecycleCallbacks]
class ProductReferenceProposal
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private User $proposedBy;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Shop $shop;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $nameFr;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameAr = null;

    #[ORM\ManyToOne(targetEntity: Brand::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Brand $brand = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $brandName = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private Category $category;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $variantFr = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3, nullable: true)]
    private ?string $volume = null;

    #[ORM\Column(length: 32, enumType: ProductUnit::class)]
    private ProductUnit $unit = ProductUnit::Piece;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(length: 32, enumType: ProductReferenceProposalStatus::class)]
    private ProductReferenceProposalStatus $status = ProductReferenceProposalStatus::Pending;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\ManyToOne(targetEntity: ProductReference::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProductReference $createdProductReference = null;

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

    public function getProposedBy(): User
    {
        return $this->proposedBy;
    }

    public function setProposedBy(User $proposedBy): static
    {
        $this->proposedBy = $proposedBy;

        return $this;
    }

    public function getShop(): Shop
    {
        return $this->shop;
    }

    public function setShop(Shop $shop): static
    {
        $this->shop = $shop;

        return $this;
    }

    public function getNameFr(): string
    {
        return $this->nameFr;
    }

    public function setNameFr(string $nameFr): static
    {
        $this->nameFr = $nameFr;

        return $this;
    }

    public function getNameAr(): ?string
    {
        return $this->nameAr;
    }

    public function setNameAr(?string $nameAr): static
    {
        $this->nameAr = $nameAr;

        return $this;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getBrandName(): ?string
    {
        return $this->brandName;
    }

    public function setBrandName(?string $brandName): static
    {
        $this->brandName = $brandName;

        return $this;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getVariantFr(): ?string
    {
        return $this->variantFr;
    }

    public function setVariantFr(?string $variantFr): static
    {
        $this->variantFr = $variantFr;

        return $this;
    }

    public function getVolume(): ?string
    {
        return $this->volume;
    }

    public function setVolume(?string $volume): static
    {
        $this->volume = $volume;

        return $this;
    }

    public function getUnit(): ProductUnit
    {
        return $this->unit;
    }

    public function setUnit(ProductUnit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): static
    {
        $this->barcode = $barcode;

        return $this;
    }

    public function getStatus(): ProductReferenceProposalStatus
    {
        return $this->status;
    }

    public function setStatus(ProductReferenceProposalStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }

    public function getCreatedProductReference(): ?ProductReference
    {
        return $this->createdProductReference;
    }

    public function setCreatedProductReference(?ProductReference $createdProductReference): static
    {
        $this->createdProductReference = $createdProductReference;

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
