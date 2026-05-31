<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MerchantProductPriceChangeType;
use App\Enum\MerchantProductPriceSource;
use App\Repository\MerchantProductPriceHistoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MerchantProductPriceHistoryRepository::class)]
#[ORM\Table(name: 'merchant_product_price_history')]
#[ORM\Index(name: 'IDX_MERCHANT_PRODUCT_PRICE_HISTORY_PRODUCT', columns: ['merchant_product_id'])]
#[ORM\Index(name: 'IDX_MERCHANT_PRODUCT_PRICE_HISTORY_MERCHANT', columns: ['merchant_id'])]
#[ORM\Index(name: 'IDX_MERCHANT_PRODUCT_PRICE_HISTORY_CHANGED_BY', columns: ['changed_by_user_id'])]
#[ORM\Index(name: 'IDX_MERCHANT_PRODUCT_PRICE_HISTORY_CHANGED_AT', columns: ['changed_at'])]
class MerchantProductPriceHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: MerchantProduct::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MerchantProduct $merchantProduct;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'merchant_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $merchant;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3, nullable: true)]
    private ?string $oldPrice;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    #[Assert\Positive]
    private string $newPrice;

    #[ORM\Column(length: 3)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[A-Z]{3}$/')]
    private string $currency;

    #[ORM\Column(length: 32, enumType: MerchantProductPriceChangeType::class)]
    private MerchantProductPriceChangeType $changeType;

    #[ORM\Column(length: 32, enumType: MerchantProductPriceSource::class)]
    private MerchantProductPriceSource $source;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $reason;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $changedByUser;

    #[ORM\Column]
    private \DateTimeImmutable $changedAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        MerchantProduct $merchantProduct,
        ?User $merchant,
        ?string $oldPrice,
        string $newPrice,
        string $currency,
        MerchantProductPriceChangeType $changeType,
        MerchantProductPriceSource $source,
        ?User $changedByUser = null,
        ?string $reason = null,
        ?\DateTimeImmutable $changedAt = null,
    ) {
        $this->id = Uuid::v4();
        $this->merchantProduct = $merchantProduct;
        $this->merchant = $merchant;
        $this->oldPrice = null === $oldPrice ? null : bcadd($oldPrice, '0', 3);
        $this->newPrice = bcadd($newPrice, '0', 3);
        $this->currency = strtoupper($currency);
        $this->changeType = $changeType;
        $this->source = $source;
        $this->changedByUser = $changedByUser;
        $this->reason = $reason;
        $this->changedAt = $changedAt ?? new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getMerchantProduct(): MerchantProduct
    {
        return $this->merchantProduct;
    }

    public function getMerchant(): ?User
    {
        return $this->merchant;
    }

    public function getOldPrice(): ?string
    {
        return null === $this->oldPrice ? null : bcadd($this->oldPrice, '0', 3);
    }

    public function getNewPrice(): string
    {
        return bcadd($this->newPrice, '0', 3);
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getChangeType(): MerchantProductPriceChangeType
    {
        return $this->changeType;
    }

    public function getSource(): MerchantProductPriceSource
    {
        return $this->source;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getChangedByUser(): ?User
    {
        return $this->changedByUser;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
