<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProductGroupStatus;
use App\Enum\ProductGroupVisibility;
use App\Repository\ProductGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductGroupRepository::class)]
#[ORM\Table(name: 'product_groups')]
#[ORM\Index(name: 'IDX_PRODUCT_GROUPS_STATUS', columns: ['status'])]
#[ORM\Index(name: 'IDX_PRODUCT_GROUPS_SORT_ORDER', columns: ['sort_order'])]
#[ORM\HasLifecycleCallbacks]
class ProductGroup
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $nameFr;

    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $nameAr = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionFr = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionAr = null;

    #[ORM\Column(length: 2)]
    private string $marketCountry = 'TN';

    #[ORM\Column(length: 32, enumType: ProductGroupStatus::class)]
    private ProductGroupStatus $status = ProductGroupStatus::Draft;

    #[ORM\Column(length: 32, enumType: ProductGroupVisibility::class)]
    private ProductGroupVisibility $visibility = ProductGroupVisibility::Merchant;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $icon = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    /** @var Collection<int, ProductGroupItem> */
    #[ORM\OneToMany(targetEntity: ProductGroupItem::class, mappedBy: 'productGroup', cascade: ['persist', 'remove'], orphanRemoval: true)]
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescriptionFr(): ?string
    {
        return $this->descriptionFr;
    }

    public function setDescriptionFr(?string $descriptionFr): static
    {
        $this->descriptionFr = $descriptionFr;

        return $this;
    }

    public function getDescriptionAr(): ?string
    {
        return $this->descriptionAr;
    }

    public function setDescriptionAr(?string $descriptionAr): static
    {
        $this->descriptionAr = $descriptionAr;

        return $this;
    }

    public function getMarketCountry(): string
    {
        return $this->marketCountry;
    }

    public function setMarketCountry(string $marketCountry): static
    {
        $this->marketCountry = $marketCountry;

        return $this;
    }

    public function getStatus(): ProductGroupStatus
    {
        return $this->status;
    }

    public function setStatus(ProductGroupStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getVisibility(): ProductGroupVisibility
    {
        return $this->visibility;
    }

    public function setVisibility(ProductGroupVisibility $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

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

    /** @return Collection<int, ProductGroupItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /** @return Collection<int, ProductGroupItem> */
    public function getSortedItems(): Collection
    {
        $items = $this->items->toArray();
        usort(
            $items,
            static fn (ProductGroupItem $left, ProductGroupItem $right): int => [$left->getSortOrder(), $left->getProductReference()->getNameFr()]
                <=> [$right->getSortOrder(), $right->getProductReference()->getNameFr()],
        );

        return new ArrayCollection($items);
    }

    public function addItem(ProductGroupItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setProductGroup($this);
        }

        return $this;
    }

    public function removeItem(ProductGroupItem $item): static
    {
        $this->items->removeElement($item);

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
