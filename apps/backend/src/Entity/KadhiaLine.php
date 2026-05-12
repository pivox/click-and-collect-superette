<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\KadhiaLineRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: KadhiaLineRepository::class)]
#[ORM\Table(name: 'kadhia_lines')]
#[ORM\UniqueConstraint(name: 'UNIQ_KADHIA_LINES_KADHIA_PRODUCT', columns: ['kadhia_id', 'merchant_product_id'])]
#[ORM\Index(name: 'IDX_KADHIA_LINES_KADHIA', columns: ['kadhia_id'])]
#[ORM\HasLifecycleCallbacks]
class KadhiaLine
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Kadhia::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Kadhia $kadhia;

    #[ORM\ManyToOne(targetEntity: MerchantProduct::class)]
    #[ORM\JoinColumn(nullable: false)]
    private MerchantProduct $merchantProduct;

    #[ORM\Column]
    #[Assert\Positive]
    private int $quantity;

    // Snapshot of merchant offer price at the time the line was added.
    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $unitPriceTnd;

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

    public function getKadhia(): Kadhia
    {
        return $this->kadhia;
    }

    public function setKadhia(Kadhia $kadhia): static
    {
        $this->kadhia = $kadhia;

        return $this;
    }

    public function getMerchantProduct(): MerchantProduct
    {
        return $this->merchantProduct;
    }

    public function setMerchantProduct(MerchantProduct $merchantProduct): static
    {
        $this->merchantProduct = $merchantProduct;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnitPriceTnd(): string
    {
        return $this->unitPriceTnd;
    }

    public function setUnitPriceTnd(string $unitPriceTnd): static
    {
        $this->unitPriceTnd = $unitPriceTnd;

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
