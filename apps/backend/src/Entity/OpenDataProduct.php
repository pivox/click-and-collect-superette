<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OpenDataProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: OpenDataProductRepository::class)]
#[ORM\Table(name: 'open_data_products')]
#[ORM\HasLifecycleCallbacks]
class OpenDataProduct
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 30, unique: true)]
    private string $barcode;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameFr = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameAr = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $categoryFr = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $quantity = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageThumbUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ingredients = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $allergens = null;

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $nutriscore = null;

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $ecoscore = null;

    /** @var array<string, float|null>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $nutrition = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $attributes = null;

    #[ORM\Column(length: 20)]
    private string $source;

    #[ORM\Column(length: 20)]
    private string $type;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 3, nullable: true)]
    private ?string $priceTnd = null;

    #[ORM\Column]
    private int $stock = 0;

    #[ORM\Column]
    private bool $active = false;

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

    public function getBarcode(): string
    {
        return $this->barcode;
    }

    public function setBarcode(string $barcode): static
    {
        $this->barcode = $barcode;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getNameFr(): ?string
    {
        return $this->nameFr;
    }

    public function setNameFr(?string $nameFr): static
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

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getCategoryFr(): ?string
    {
        return $this->categoryFr;
    }

    public function setCategoryFr(?string $categoryFr): static
    {
        $this->categoryFr = $categoryFr;

        return $this;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(?string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getImageThumbUrl(): ?string
    {
        return $this->imageThumbUrl;
    }

    public function setImageThumbUrl(?string $imageThumbUrl): static
    {
        $this->imageThumbUrl = $imageThumbUrl;

        return $this;
    }

    public function getIngredients(): ?string
    {
        return $this->ingredients;
    }

    public function setIngredients(?string $ingredients): static
    {
        $this->ingredients = $ingredients;

        return $this;
    }

    public function getAllergens(): ?string
    {
        return $this->allergens;
    }

    public function setAllergens(?string $allergens): static
    {
        $this->allergens = $allergens;

        return $this;
    }

    public function getNutriscore(): ?string
    {
        return $this->nutriscore;
    }

    public function setNutriscore(?string $nutriscore): static
    {
        $this->nutriscore = $nutriscore;

        return $this;
    }

    public function getEcoscore(): ?string
    {
        return $this->ecoscore;
    }

    public function setEcoscore(?string $ecoscore): static
    {
        $this->ecoscore = $ecoscore;

        return $this;
    }

    /** @return array<string, float|null>|null */
    public function getNutrition(): ?array
    {
        return $this->nutrition;
    }

    /** @param array<string, float|null>|null $nutrition */
    public function setNutrition(?array $nutrition): static
    {
        $this->nutrition = $nutrition;

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

    /** @return array<string, mixed>|null */
    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    /** @param array<string, mixed>|null $attributes */
    public function setAttributes(?array $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPriceTnd(): ?string
    {
        return $this->priceTnd;
    }

    public function setPriceTnd(?string $priceTnd): static
    {
        $this->priceTnd = $priceTnd;

        return $this;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

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
}
