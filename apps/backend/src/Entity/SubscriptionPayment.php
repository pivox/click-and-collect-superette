<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SubscriptionPaymentMethod;
use App\Enum\SubscriptionPaymentStatus;
use App\Repository\SubscriptionPaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SubscriptionPaymentRepository::class)]
#[ORM\Table(name: 'subscription_payments')]
#[ORM\UniqueConstraint(name: 'UNIQ_SUBSCRIPTION_PAYMENTS_DOCUMENT', columns: ['billing_document_id'])]
#[ORM\Index(name: 'IDX_SUBSCRIPTION_PAYMENTS_SUBSCRIPTION', columns: ['subscription_id'])]
#[ORM\Index(name: 'IDX_SUBSCRIPTION_PAYMENTS_MERCHANT', columns: ['merchant_id'])]
#[ORM\Index(name: 'IDX_SUBSCRIPTION_PAYMENTS_STATUS', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class SubscriptionPayment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: BillingDocument::class)]
    #[ORM\JoinColumn(name: 'billing_document_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private BillingDocument $billingDocument;

    #[ORM\ManyToOne(targetEntity: Subscription::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Subscription $subscription;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private User $merchant;

    #[ORM\Column(name: 'amount_tnd', type: 'decimal', precision: 10, scale: 3)]
    #[Assert\Positive]
    private string $amountTnd = '0.000';

    #[ORM\Column(length: 3)]
    private string $currency = 'TND';

    #[ORM\Column(name: 'period_start')]
    private \DateTimeImmutable $periodStart;

    #[ORM\Column(name: 'period_end')]
    private \DateTimeImmutable $periodEnd;

    #[ORM\Column(length: 20, enumType: SubscriptionPaymentMethod::class)]
    private SubscriptionPaymentMethod $method;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(name: 'paid_at')]
    private \DateTimeImmutable $paidAt;

    #[ORM\Column(length: 20, enumType: SubscriptionPaymentStatus::class)]
    private SubscriptionPaymentStatus $status = SubscriptionPaymentStatus::Confirmed;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_admin_id', nullable: false, onDelete: 'RESTRICT')]
    private User $createdByAdmin;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'validated_by_admin_id', nullable: false, onDelete: 'RESTRICT')]
    private User $validatedByAdmin;

    #[ORM\Column(name: 'validated_at')]
    private \DateTimeImmutable $validatedAt;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->id = Uuid::v4();
        $this->periodStart = $now;
        $this->periodEnd = $now->modify('+1 month');
        $this->method = SubscriptionPaymentMethod::Cash;
        $this->paidAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->validatedAt = $now;
    }

    public static function confirm(
        BillingDocument $billingDocument,
        string $amountTnd,
        SubscriptionPaymentMethod $method,
        \DateTimeImmutable $paidAt,
        User $createdByAdmin,
        ?string $reference,
    ): self {
        $payment = new self();
        $payment->billingDocument = $billingDocument;
        $payment->subscription = $billingDocument->getSubscription();
        $payment->merchant = $billingDocument->getMerchant();
        $payment->amountTnd = bcadd($amountTnd, '0', 3);
        $payment->currency = $billingDocument->getCurrency();
        $payment->periodStart = $billingDocument->getBillingPeriodStart();
        $payment->periodEnd = $billingDocument->getBillingPeriodEnd();
        $payment->method = $method;
        $payment->reference = null !== $reference && '' !== trim($reference) ? trim($reference) : null;
        $payment->paidAt = $paidAt;
        $payment->status = SubscriptionPaymentStatus::Confirmed;
        $payment->createdByAdmin = $createdByAdmin;
        $payment->validatedByAdmin = $createdByAdmin;
        $payment->validatedAt = new \DateTimeImmutable();

        return $payment;
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

    public function getBillingDocument(): BillingDocument
    {
        return $this->billingDocument;
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function getMerchant(): User
    {
        return $this->merchant;
    }

    public function getAmountTnd(): string
    {
        return bcadd($this->amountTnd, '0', 3);
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getPeriodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function getPeriodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function getMethod(): SubscriptionPaymentMethod
    {
        return $this->method;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getPaidAt(): \DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getStatus(): SubscriptionPaymentStatus
    {
        return $this->status;
    }

    public function getCreatedByAdmin(): User
    {
        return $this->createdByAdmin;
    }

    public function getValidatedByAdmin(): User
    {
        return $this->validatedByAdmin;
    }

    public function getValidatedAt(): \DateTimeImmutable
    {
        return $this->validatedAt;
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
