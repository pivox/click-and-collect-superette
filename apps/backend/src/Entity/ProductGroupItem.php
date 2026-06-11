<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProductGroupItemImportance;
use App\Repository\ProductGroupItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProductGroupItemRepository::class)]
#[ORM\Table(name: 'product_group_items')]
#[ORM\UniqueConstraint(name: 'UNIQ_PRODUCT_GROUP_ITEMS_GROUP_REFERENCE', columns: ['product_group_id', 'product_reference_id'])]
#[ORM\Index(name: 'IDX_PRODUCT_GROUP_ITEMS_GROUP', columns: ['product_group_id'])]
#[ORM\Index(name: 'IDX_PRODUCT_GROUP_ITEMS_REFERENCE', columns: ['product_reference_id'])]
#[ORM\HasLifecycleCallbacks]
class ProductGroupItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: ProductGroup::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProductGroup $productGroup;

    #[ORM\ManyToOne(targetEntity: ProductReference::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProductReference $productReference;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(length: 32, enumType: ProductGroupItemImportance::class)]
    private ProductGroupItemImportance $importance = ProductGroupItemImportance::Recommended;

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

    public function getProductGroup(): ProductGroup
    {
        return $this->productGroup;
    }

    public function setProductGroup(ProductGroup $productGroup): static
    {
        $this->productGroup = $productGroup;

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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getImportance(): ProductGroupItemImportance
    {
        return $this->importance;
    }

    public function setImportance(ProductGroupItemImportance $importance): static
    {
        $this->importance = $importance;

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
