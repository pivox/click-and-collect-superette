<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderLineRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderLineRepository::class)]
#[ORM\Table(name: 'order_lines')]
#[ORM\UniqueConstraint(name: 'UNIQ_ORDER_LINES_ORDER_PRODUCT', columns: ['order_id', 'merchant_product_id'])]
#[ORM\Index(name: 'IDX_ORDER_LINES_ORDER', columns: ['order_id'])]
#[ORM\HasLifecycleCallbacks]
class OrderLine
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: MerchantProduct::class)]
    #[ORM\JoinColumn(nullable: false)]
    private MerchantProduct $merchantProduct;

    #[ORM\Column]
    #[Assert\Positive]
    private int $quantity;

    // Snapshot of merchant offer price at order creation time.
    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $unitPriceTnd;

    // Stored for reporting: quantity * unitPriceTnd computed at creation.
    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $lineTotalTnd;

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

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): static
    {
        $this->order = $order;

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

    public function getLineTotalTnd(): string
    {
        return $this->lineTotalTnd;
    }

    public function setLineTotalTnd(string $lineTotalTnd): static
    {
        $this->lineTotalTnd = $lineTotalTnd;

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
