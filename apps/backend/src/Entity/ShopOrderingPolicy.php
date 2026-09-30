<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShopOrderingPolicyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Per-shop ordering policy (ORDER-LEAD-001).
 *
 * Historical shops have no row: the effective policy then resolves to the
 * defaults below. A row is created lazily on the first merchant PATCH.
 */
#[ORM\Entity(repositoryClass: ShopOrderingPolicyRepository::class)]
#[ORM\Table(name: 'shop_ordering_policies')]
#[ORM\HasLifecycleCallbacks]
class ShopOrderingPolicy
{
    public const DEFAULT_MINIMUM_PICKUP_LEAD_TIME_MINUTES = 0;
    public const MAX_MINIMUM_PICKUP_LEAD_TIME_MINUTES = 10080;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(inversedBy: 'orderingPolicy', targetEntity: Shop::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Shop $shop = null;

    #[ORM\Column(options: ['default' => self::DEFAULT_MINIMUM_PICKUP_LEAD_TIME_MINUTES])]
    #[Assert\Range(min: 0, max: self::MAX_MINIMUM_PICKUP_LEAD_TIME_MINUTES)]
    private int $minimumPickupLeadTimeMinutes = self::DEFAULT_MINIMUM_PICKUP_LEAD_TIME_MINUTES;

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

    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function setShop(Shop $shop): self
    {
        $this->shop = $shop;

        return $this;
    }

    public function getMinimumPickupLeadTimeMinutes(): int
    {
        return $this->minimumPickupLeadTimeMinutes;
    }

    public function setMinimumPickupLeadTimeMinutes(int $minutes): self
    {
        $this->minimumPickupLeadTimeMinutes = $minutes;

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
