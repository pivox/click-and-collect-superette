<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PickupSlotRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PickupSlotRepository::class)]
#[ORM\Table(name: 'pickup_slots')]
#[ORM\Index(name: 'IDX_PICKUP_SLOTS_SHOP', columns: ['shop_id'])]
#[ORM\Index(name: 'IDX_PICKUP_SLOTS_SHOP_STARTS_AT', columns: ['shop_id', 'starts_at'])]
#[ORM\HasLifecycleCallbacks]
class PickupSlot
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shop $shop;

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column]
    #[Assert\Positive]
    private int $capacity;

    #[ORM\Column]
    private int $bookedCount = 0;

    #[ORM\Column]
    private bool $isActive = true;

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

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getBookedCount(): int
    {
        return $this->bookedCount;
    }

    public function getAvailableCount(): int
    {
        return max(0, $this->capacity - $this->bookedCount);
    }

    public function isFull(): bool
    {
        return $this->bookedCount >= $this->capacity;
    }

    public function book(): void
    {
        if ($this->isFull()) {
            throw new \RuntimeException('PICKUP_SLOT_FULL');
        }

        ++$this->bookedCount;
    }

    public function unbook(): void
    {
        if ($this->bookedCount > 0) {
            --$this->bookedCount;
        }
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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
