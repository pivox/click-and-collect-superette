<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SubscriptionLifecycle;
use App\Enum\SubscriptionPricingPhase;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
#[ORM\UniqueConstraint(name: 'UNIQ_SUBSCRIPTIONS_MERCHANT', columns: ['merchant_id'])]
#[ORM\HasLifecycleCallbacks]
class Subscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private User $merchant;

    #[ORM\Column(length: 20, enumType: SubscriptionLifecycle::class)]
    private SubscriptionLifecycle $lifecycle = SubscriptionLifecycle::Active;

    #[ORM\Column(length: 20, enumType: SubscriptionPricingPhase::class)]
    private SubscriptionPricingPhase $pricingPhase = SubscriptionPricingPhase::Trial;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private string $monthlyPriceTnd = '0.000';

    #[ORM\Column(length: 3)]
    private string $currency = 'TND';

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column]
    private \DateTimeImmutable $currentPeriodStartedAt;

    #[ORM\Column]
    private \DateTimeImmutable $currentPeriodEndsAt;

    #[ORM\Column]
    private \DateTimeImmutable $trialEndsAt;

    #[ORM\Column]
    private \DateTimeImmutable $promoEndsAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $nextPhaseChangeAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->startedAt = new \DateTimeImmutable();
        $this->currentPeriodStartedAt = $this->startedAt;
        $this->currentPeriodEndsAt = $this->startedAt->modify('+1 month');
        $this->trialEndsAt = $this->startedAt->modify('+3 months');
        $this->promoEndsAt = $this->startedAt->modify('+6 months');
        $this->nextPhaseChangeAt = $this->trialEndsAt;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function startTrial(User $merchant, \DateTimeImmutable $startedAt): self
    {
        $subscription = new self();
        $subscription->merchant = $merchant;
        $subscription->startedAt = $startedAt;
        $subscription->trialEndsAt = $startedAt->modify('+3 months');
        $subscription->promoEndsAt = $startedAt->modify('+6 months');
        $subscription->currentPeriodStartedAt = $startedAt;
        $subscription->currentPeriodEndsAt = $startedAt->modify('+1 month');
        $subscription->nextPhaseChangeAt = $subscription->trialEndsAt;
        $subscription->lifecycle = SubscriptionLifecycle::Active;
        $subscription->pricingPhase = SubscriptionPricingPhase::Trial;
        $subscription->monthlyPriceTnd = '0.000';

        return $subscription;
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

    public function getMerchant(): User
    {
        return $this->merchant;
    }

    public function setMerchant(User $merchant): static
    {
        $this->merchant = $merchant;

        return $this;
    }

    public function getLifecycle(): SubscriptionLifecycle
    {
        return $this->lifecycle;
    }

    public function setLifecycle(SubscriptionLifecycle $lifecycle): static
    {
        $this->lifecycle = $lifecycle;
        if (SubscriptionLifecycle::Cancelled === $lifecycle && null === $this->cancelledAt) {
            $this->cancelledAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getPricingPhase(): SubscriptionPricingPhase
    {
        return $this->pricingPhase;
    }

    public function setPricingPhase(SubscriptionPricingPhase $pricingPhase): static
    {
        $this->pricingPhase = $pricingPhase;

        return $this;
    }

    public function getMonthlyPriceTnd(): string
    {
        return bcadd($this->monthlyPriceTnd, '0', 3);
    }

    public function setMonthlyPriceTnd(string $monthlyPriceTnd): static
    {
        $this->monthlyPriceTnd = bcadd($monthlyPriceTnd, '0', 3);

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCurrentPeriodStartedAt(): \DateTimeImmutable
    {
        return $this->currentPeriodStartedAt;
    }

    public function setCurrentPeriodStartedAt(\DateTimeImmutable $currentPeriodStartedAt): static
    {
        $this->currentPeriodStartedAt = $currentPeriodStartedAt;

        return $this;
    }

    public function getCurrentPeriodEndsAt(): \DateTimeImmutable
    {
        return $this->currentPeriodEndsAt;
    }

    public function setCurrentPeriodEndsAt(\DateTimeImmutable $currentPeriodEndsAt): static
    {
        $this->currentPeriodEndsAt = $currentPeriodEndsAt;

        return $this;
    }

    public function getTrialEndsAt(): \DateTimeImmutable
    {
        return $this->trialEndsAt;
    }

    public function getPromoEndsAt(): \DateTimeImmutable
    {
        return $this->promoEndsAt;
    }

    public function getNextPhaseChangeAt(): ?\DateTimeImmutable
    {
        return $this->nextPhaseChangeAt;
    }

    public function setNextPhaseChangeAt(?\DateTimeImmutable $nextPhaseChangeAt): static
    {
        $this->nextPhaseChangeAt = $nextPhaseChangeAt;

        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
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
