<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Repository\ProductReferenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductReferenceRepository::class)]
#[ORM\Table(name: 'product_references')]
#[ORM\HasLifecycleCallbacks]
class ProductReference
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Brand::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private Brand $brand;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private Category $category;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $nameFr;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameAr = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $variantFr = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $variantAr = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3, nullable: true)]
    private ?string $volume = null;

    #[ORM\Column(length: 32, enumType: ProductUnit::class)]
    private ProductUnit $unit = ProductUnit::Piece;

    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $barcode = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $aliases = [];

    #[ORM\Column(length: 2)]
    private string $country = 'TN';

    #[ORM\Column(length: 32, enumType: ProductReferenceStatus::class)]
    private ProductReferenceStatus $status = ProductReferenceStatus::Draft;

    #[ORM\ManyToOne(targetEntity: ProductFamily::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProductFamily $productFamily = null;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    #[Assert\GreaterThanOrEqual(1)]
    private int $packQuantity = 1;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $aiEnrichedAt = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 3, nullable: true)]
    private ?string $aiConfidence = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $aiSource = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $aiPreviousValues = null;

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

    public function getBrand(): Brand
    {
        return $this->brand;
    }

    public function setBrand(Brand $brand): static
    {
        $this->brand = $brand;

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

    public function getVariantFr(): ?string
    {
        return $this->variantFr;
    }

    public function setVariantFr(?string $variantFr): static
    {
        $this->variantFr = $variantFr;

        return $this;
    }

    public function getVariantAr(): ?string
    {
        return $this->variantAr;
    }

    public function setVariantAr(?string $variantAr): static
    {
        $this->variantAr = $variantAr;

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

    /**
     * @return list<string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * @param list<string> $aliases
     */
    public function setAliases(array $aliases): static
    {
        $this->aliases = $aliases;

        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getStatus(): ProductReferenceStatus
    {
        return $this->status;
    }

    public function setStatus(ProductReferenceStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getProductFamily(): ?ProductFamily
    {
        return $this->productFamily;
    }

    public function setProductFamily(?ProductFamily $productFamily): static
    {
        $this->productFamily = $productFamily;

        return $this;
    }

    public function getPackQuantity(): int
    {
        return $this->packQuantity;
    }

    public function setPackQuantity(int $packQuantity): static
    {
        $this->packQuantity = $packQuantity;

        return $this;
    }

    public function getAiEnrichedAt(): ?\DateTimeImmutable
    {
        return $this->aiEnrichedAt;
    }

    public function setAiEnrichedAt(?\DateTimeImmutable $aiEnrichedAt): static
    {
        $this->aiEnrichedAt = $aiEnrichedAt;

        return $this;
    }

    public function getAiConfidence(): ?string
    {
        return null === $this->aiConfidence ? null : bcadd($this->aiConfidence, '0', 3);
    }

    public function setAiConfidence(?string $aiConfidence): static
    {
        $this->aiConfidence = null === $aiConfidence ? null : bcadd($aiConfidence, '0', 3);

        return $this;
    }

    public function getAiSource(): ?string
    {
        return $this->aiSource;
    }

    public function setAiSource(?string $aiSource): static
    {
        $this->aiSource = $aiSource;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getAiPreviousValues(): ?array
    {
        return $this->aiPreviousValues;
    }

    /** @param array<string, mixed>|null $aiPreviousValues */
    public function setAiPreviousValues(?array $aiPreviousValues): static
    {
        $this->aiPreviousValues = $aiPreviousValues;

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
