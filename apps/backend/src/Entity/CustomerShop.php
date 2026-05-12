<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CustomerShopSource;
use App\Enum\CustomerShopStatus;
use App\Repository\CustomerShopRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CustomerShopRepository::class)]
#[ORM\Table(name: 'customer_shops')]
#[ORM\UniqueConstraint(name: 'UNIQ_CUSTOMER_SHOPS_CUSTOMER_SHOP', columns: ['customer_id', 'shop_id'])]
#[ORM\Index(name: 'IDX_CUSTOMER_SHOPS_CUSTOMER', columns: ['customer_id'])]
#[ORM\Index(name: 'IDX_CUSTOMER_SHOPS_SHOP', columns: ['shop_id'])]
#[ORM\HasLifecycleCallbacks]
class CustomerShop
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

    #[ORM\Column(type: 'string', length: 32, enumType: CustomerShopSource::class)]
    private CustomerShopSource $source;

    #[ORM\Column]
    private \DateTimeImmutable $firstSeenAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column]
    private bool $isFavorite = false;

    #[ORM\Column(type: 'string', length: 16, enumType: CustomerShopStatus::class)]
    private CustomerShopStatus $status = CustomerShopStatus::Active;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->id = Uuid::v4();
        $this->firstSeenAt = $now;
        $this->lastSeenAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function getSource(): CustomerShopSource
    {
        return $this->source;
    }

    public function setSource(CustomerShopSource $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getFirstSeenAt(): \DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function touchLastSeenAt(): static
    {
        $this->lastSeenAt = new \DateTimeImmutable();

        return $this;
    }

    public function isFavorite(): bool
    {
        return $this->isFavorite;
    }

    public function setFavorite(bool $isFavorite): static
    {
        $this->isFavorite = $isFavorite;

        return $this;
    }

    public function getStatus(): CustomerShopStatus
    {
        return $this->status;
    }

    public function setStatus(CustomerShopStatus $status): static
    {
        $this->status = $status;

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
