<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProductUnit;
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
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductReference $productReference = null;

    #[ORM\ManyToOne(targetEntity: MerchantLocalProduct::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?MerchantLocalProduct $localProduct = null;

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

    public function getProductReference(): ?ProductReference
    {
        return $this->productReference;
    }

    public function setProductReference(?ProductReference $productReference): static
    {
        $this->productReference = $productReference;
        if (null !== $productReference) {
            $this->localProduct = null;
        }

        return $this;
    }

    public function getLocalProduct(): ?MerchantLocalProduct
    {
        return $this->localProduct;
    }

    public function setLocalProduct(?MerchantLocalProduct $localProduct): static
    {
        $this->localProduct = $localProduct;
        if (null !== $localProduct) {
            $this->productReference = null;
        }

        return $this;
    }

    public function hasExactlyOneProductSource(): bool
    {
        return (null !== $this->productReference) xor (null !== $this->localProduct);
    }

    public function getDisplayNameFr(): string
    {
        return $this->productReference?->getNameFr() ?? $this->requireLocalProduct()->getNameFr();
    }

    public function getDisplayNameAr(): ?string
    {
        return $this->productReference?->getNameAr() ?? $this->localProduct?->getNameAr();
    }

    public function getDisplayBrandName(): ?string
    {
        return $this->productReference?->getBrand()->getCanonicalName() ?? $this->localProduct?->getBrandName();
    }

    public function getDisplayCategoryName(): string
    {
        return $this->productReference?->getCategory()->getNameFr() ?? $this->requireLocalProduct()->getCatalogCategoryName();
    }

    public function getDisplayCategoryNameAr(): ?string
    {
        return $this->productReference?->getCategory()->getNameAr();
    }

    public function getDisplayCategorySlug(): string
    {
        $referenceCategory = $this->productReference?->getCategory();
        if (null !== $referenceCategory) {
            return $referenceCategory->getSlug();
        }

        return $this->slugify($this->requireLocalProduct()->getCatalogCategoryName());
    }

    public function getDisplayVolume(): ?string
    {
        $volume = $this->productReference?->getVolume() ?? $this->localProduct?->getVolume();
        if (null === $volume) {
            return null;
        }

        return bcadd($volume, '0', 3);
    }

    public function getDisplayUnit(): ProductUnit
    {
        return $this->productReference?->getUnit() ?? $this->requireLocalProduct()->getUnit();
    }

    private function requireLocalProduct(): MerchantLocalProduct
    {
        if (null === $this->localProduct) {
            throw new \LogicException('Merchant product must have exactly one product source.');
        }

        return $this->localProduct;
    }

    private function slugify(string $value): string
    {
        $value = strtolower($value);
        $value = strtr($value, [
            'à' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ç' => 'c',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'î' => 'i',
            'ï' => 'i',
            'ô' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
        ]);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $normalized = false === $transliterated ? $value : $transliterated;
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($normalized)) ?? '';
        $slug = trim($slug, '-');

        return '' === $slug ? 'produit-local' : $slug;
    }

    public function getPriceTnd(): string
    {
        return bcadd($this->priceTnd, '0', 3);
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
