<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductPackRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductPackRepository::class)]
#[ORM\Table(name: 'product_packs')]
#[ORM\Index(name: 'IDX_PRODUCT_PACKS_SHOP', columns: ['shop_id'])]
#[ORM\HasLifecycleCallbacks]
class ProductPack
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
    #[Assert\Length(max: 255)]
    private string $nameFr;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $nameAr = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $isActive = true;

    /** @var Collection<int, ProductPackItem> */
    #[ORM\OneToMany(targetEntity: ProductPackItem::class, mappedBy: 'pack', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /** @return Collection<int, ProductPackItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ProductPackItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setPack($this);
        }

        return $this;
    }

    public function removeItem(ProductPackItem $item): static
    {
        if ($this->items->removeElement($item)) {
            // Can't set pack to null since it's required, just remove the item
        }

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
