<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProductUnit;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'merchant_local_products')]
#[ORM\Index(name: 'IDX_MERCHANT_LOCAL_PRODUCTS_SHOP', columns: ['shop_id'])]
#[ORM\HasLifecycleCallbacks]
class MerchantLocalProduct
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Shop $shop;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $nameFr;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameAr = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $brandName = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3, nullable: true)]
    private ?string $volume = null;

    #[ORM\Column(length: 32, enumType: ProductUnit::class)]
    private ProductUnit $unit = ProductUnit::Piece;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $defaultCategoryName = null;

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

    public function getBrandName(): ?string
    {
        return $this->brandName;
    }

    public function setBrandName(?string $brandName): static
    {
        $this->brandName = $brandName;

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

    public function getDefaultCategoryName(): ?string
    {
        return $this->defaultCategoryName;
    }

    public function setDefaultCategoryName(?string $defaultCategoryName): static
    {
        $this->defaultCategoryName = $defaultCategoryName;

        return $this;
    }

    public function getCatalogCategoryName(): string
    {
        return $this->defaultCategoryName ?? 'Produit local';
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
