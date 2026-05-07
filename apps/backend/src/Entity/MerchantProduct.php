<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MerchantProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MerchantProductRepository::class)]
#[ORM\Table(name: 'merchant_products')]
#[ORM\UniqueConstraint(name: 'UNIQ_MERCHANT_PRODUCTS_SHOP_REF', columns: ['shop_id', 'product_reference_id'])]
#[ORM\HasLifecycleCallbacks]
class MerchantProduct
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private Shop $shop;

    #[ORM\ManyToOne(targetEntity: ProductReference::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ProductReference $productReference;

    // Price owned by the merchant offer, not the shared product reference.
    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private string $priceTnd;

    #[ORM\Column]
    private bool $isAvailable = true;

    #[ORM\Column]
    private bool $isVisible = true;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $merchantNote = null;

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

    public function getProductReference(): ProductReference
    {
        return $this->productReference;
    }

    public function setProductReference(ProductReference $productReference): static
    {
        $this->productReference = $productReference;

        return $this;
    }

    public function getPriceTnd(): string
    {
        return $this->priceTnd;
    }

    public function setPriceTnd(string $priceTnd): static
    {
        $this->priceTnd = $priceTnd;

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setAvailable(bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->isVisible;
    }

    public function setVisible(bool $isVisible): static
    {
        $this->isVisible = $isVisible;

        return $this;
    }

    public function getMerchantNote(): ?string
    {
        return $this->merchantNote;
    }

    public function setMerchantNote(?string $merchantNote): static
    {
        $this->merchantNote = $merchantNote;

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
