<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'IDX_ORDERS_CUSTOMER', columns: ['customer_id'])]
#[ORM\Index(name: 'IDX_ORDERS_SHOP', columns: ['shop_id'])]
#[ORM\Index(name: 'IDX_ORDERS_STATUS', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $customer;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shop $shop;

    // Nullable: the source Kadhia may be deleted after order creation.
    #[ORM\ManyToOne(targetEntity: Kadhia::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Kadhia $kadhia = null;

    // Nullable: the slot may be removed without cancelling the order.
    #[ORM\ManyToOne(targetEntity: PickupSlot::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PickupSlot $pickupSlot = null;

    #[ORM\Column(type: 'string', length: 32, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::Draft;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $rejectionReason = null;

    /** @var Collection<int, OrderLine> */
    #[ORM\OneToMany(targetEntity: OrderLine::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lines;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $totalTnd = '0.000';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
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

    public function getCustomer(): User
    {
        return $this->customer;
    }

    public function setCustomer(User $customer): static
    {
        $this->customer = $customer;

        return $this;
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

    public function getKadhia(): ?Kadhia
    {
        return $this->kadhia;
    }

    public function setKadhia(?Kadhia $kadhia): static
    {
        $this->kadhia = $kadhia;

        return $this;
    }

    public function getPickupSlot(): ?PickupSlot
    {
        return $this->pickupSlot;
    }

    public function setPickupSlot(?PickupSlot $pickupSlot): static
    {
        if (null !== $pickupSlot && $pickupSlot->getShop() !== $this->shop) {
            throw new \LogicException('PICKUP_SLOT_SHOP_MISMATCH');
        }
        $this->pickupSlot = $pickupSlot;

        return $this;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    /** @return Collection<int, OrderLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(OrderLine $line): static
    {
        if ($line->getMerchantProduct()->getShop() !== $this->shop) {
            throw new \LogicException('ORDER_LINE_SHOP_MISMATCH');
        }
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setOrder($this);
        }

        return $this;
    }

    public function removeLine(OrderLine $line): static
    {
        $this->lines->removeElement($line);

        return $this;
    }

    public function getTotalTnd(): string
    {
        return $this->totalTnd;
    }

    public function recomputeTotal(): void
    {
        $total = '0.000';
        foreach ($this->lines as $line) {
            $total = bcadd($total, $line->getLineTotalTnd(), 3);
        }
        $this->totalTnd = $total;
    }

    public function submit(): void
    {
        if (OrderStatus::Draft !== $this->status) {
            throw new \LogicException('ORDER_NOT_DRAFT');
        }
        $this->status = OrderStatus::Submitted;
    }

    public function accept(): void
    {
        if (OrderStatus::Submitted !== $this->status) {
            throw new \LogicException('ORDER_NOT_SUBMITTED');
        }
        $this->status = OrderStatus::Accepted;
    }

    public function partiallyAccept(): void
    {
        if (OrderStatus::Submitted !== $this->status) {
            throw new \LogicException('ORDER_NOT_SUBMITTED');
        }
        $this->status = OrderStatus::PartiallyAccepted;
    }

    public function resubmit(): void
    {
        if (OrderStatus::PartiallyAccepted !== $this->status) {
            throw new \LogicException('ORDER_NOT_PARTIALLY_ACCEPTED');
        }
        $this->status = OrderStatus::Submitted;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function reject(?string $reason = null): void
    {
        if (OrderStatus::Submitted !== $this->status) {
            throw new \LogicException('ORDER_NOT_SUBMITTED');
        }
        $this->status = OrderStatus::Rejected;
        $this->rejectionReason = $reason;
    }

    public function startPreparing(): void
    {
        if (OrderStatus::Accepted !== $this->status) {
            throw new \LogicException('ORDER_NOT_ACCEPTED');
        }
        $this->status = OrderStatus::Preparing;
    }

    public function markReady(): void
    {
        if (OrderStatus::Preparing !== $this->status) {
            throw new \LogicException('ORDER_NOT_PREPARING');
        }
        $this->status = OrderStatus::Ready;
    }

    public function startPickup(): void
    {
        if (OrderStatus::Ready !== $this->status) {
            throw new \LogicException('ORDER_NOT_READY');
        }
        $this->status = OrderStatus::PickupPending;
    }

    public function complete(): void
    {
        if (OrderStatus::PickupPending !== $this->status) {
            throw new \LogicException('ORDER_NOT_PICKUP_PENDING');
        }
        $this->status = OrderStatus::Completed;
    }

    public function cancel(): void
    {
        $cancellable = [OrderStatus::Draft, OrderStatus::Submitted, OrderStatus::Accepted];
        if (!\in_array($this->status, $cancellable, true)) {
            throw new \LogicException('ORDER_CANNOT_BE_CANCELLED');
        }
        $this->status = OrderStatus::Cancelled;
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
