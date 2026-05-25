<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MerchantCategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MerchantCategoryRepository::class)]
#[ORM\Table(name: 'merchant_categories')]
#[ORM\UniqueConstraint(name: 'UNIQ_MERCHANT_CATEGORIES_SHOP_NAME_FR', columns: ['shop_id', 'name_fr'])]
#[ORM\UniqueConstraint(name: 'UNIQ_MERCHANT_CATEGORIES_SHOP_SLUG', columns: ['shop_id', 'slug'])]
#[ORM\Index(name: 'IDX_MERCHANT_CATEGORIES_SHOP', columns: ['shop_id'])]
#[ORM\Index(name: 'IDX_MERCHANT_CATEGORIES_PARENT', columns: ['parent_id'])]
#[ORM\HasLifecycleCallbacks]
class MerchantCategory
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Shop $shop;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    private string $nameFr;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $slug;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $nameAr = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MerchantCategory $parent = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $active = true;

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
        $this->assertParentBelongsToShop($shop, $this->parent);
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->assertParentBelongsToShop(isset($this->shop) ? $this->shop : null, $parent);
        $this->parent = $parent;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

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

    private function assertParentBelongsToShop(?Shop $shop, ?self $parent): void
    {
        if (null === $shop || null === $parent) {
            return;
        }

        if (!$shop->getId()->equals($parent->getShop()->getId())) {
            throw new \LogicException('Merchant category parent must belong to the same shop.');
        }
    }
}
