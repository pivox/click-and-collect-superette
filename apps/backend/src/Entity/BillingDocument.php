<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BillingDocumentStatus;
use App\Enum\BillingDocumentType;
use App\Enum\SubscriptionPricingPhase;
use App\Repository\BillingDocumentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BillingDocumentRepository::class)]
#[ORM\Table(name: 'billing_documents')]
#[ORM\UniqueConstraint(name: 'UNIQ_BILLING_DOCUMENTS_NUMBER', columns: ['document_number'])]
#[ORM\Index(name: 'IDX_BILLING_DOCUMENTS_MERCHANT', columns: ['merchant_id'])]
#[ORM\Index(name: 'IDX_BILLING_DOCUMENTS_SUBSCRIPTION', columns: ['subscription_id'])]
#[ORM\Index(name: 'IDX_BILLING_DOCUMENTS_STATUS', columns: ['status'])]
#[ORM\Index(name: 'IDX_BILLING_DOCUMENTS_ISSUED_AT', columns: ['issued_at'])]
#[ORM\HasLifecycleCallbacks]
class BillingDocument
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Subscription::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Subscription $subscription;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private User $merchant;

    #[ORM\Column(name: 'document_number', length: 32)]
    #[Assert\NotBlank]
    private string $documentNumber;

    #[ORM\Column(name: 'document_type', length: 32, enumType: BillingDocumentType::class)]
    private BillingDocumentType $documentType = BillingDocumentType::MonthlyStatement;

    #[ORM\Column(length: 20, enumType: BillingDocumentStatus::class)]
    private BillingDocumentStatus $status = BillingDocumentStatus::Draft;

    #[ORM\Column(name: 'pricing_phase', length: 20, enumType: SubscriptionPricingPhase::class)]
    private SubscriptionPricingPhase $pricingPhase = SubscriptionPricingPhase::Trial;

    #[ORM\Column(length: 3)]
    private string $currency = 'TND';

    #[ORM\Column(name: 'billing_period_start')]
    private \DateTimeImmutable $billingPeriodStart;

    #[ORM\Column(name: 'billing_period_end')]
    private \DateTimeImmutable $billingPeriodEnd;

    #[ORM\Column(name: 'issued_at', nullable: true)]
    private ?\DateTimeImmutable $issuedAt = null;

    #[ORM\Column(name: 'due_at', nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(name: 'paid_at', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(name: 'cancelled_at', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(name: 'cancellation_reason', type: 'text', nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(name: 'amount_tnd', type: 'decimal', precision: 10, scale: 3)]
    #[Assert\PositiveOrZero]
    private string $amountTnd = '0.000';

    #[ORM\Column(name: 'amount_paid_tnd', type: 'decimal', precision: 10, scale: 3)]
    #[Assert\PositiveOrZero]
    private string $amountPaidTnd = '0.000';

    #[ORM\Column(name: 'amount_due_tnd', type: 'decimal', precision: 10, scale: 3)]
    #[Assert\PositiveOrZero]
    private string $amountDueTnd = '0.000';

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->id = Uuid::v4();
        $this->billingPeriodStart = $now;
        $this->billingPeriodEnd = $now->modify('+1 month');
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public static function issueMonthlyStatement(
        Subscription $subscription,
        string $documentNumber,
        \DateTimeImmutable $billingPeriodStart,
        \DateTimeImmutable $billingPeriodEnd,
        \DateTimeImmutable $issuedAt,
        \DateTimeImmutable $dueAt,
        SubscriptionPricingPhase $pricingPhase,
        string $amountTnd,
    ): self {
        if ($billingPeriodEnd <= $billingPeriodStart) {
            throw new \InvalidArgumentException('BILLING_DOCUMENT_INVALID_PERIOD');
        }

        $document = new self();
        $document->subscription = $subscription;
        $document->merchant = $subscription->getMerchant();
        $document->documentNumber = $documentNumber;
        $document->documentType = BillingDocumentType::MonthlyStatement;
        $document->status = BillingDocumentStatus::Issued;
        $document->pricingPhase = $pricingPhase;
        $document->billingPeriodStart = $billingPeriodStart;
        $document->billingPeriodEnd = $billingPeriodEnd;
        $document->issuedAt = $issuedAt;
        $document->dueAt = $dueAt;
        $document->amountTnd = bcadd($amountTnd, '0', 3);
        $document->amountPaidTnd = '0.000';
        $document->amountDueTnd = $document->amountTnd;

        return $document;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markPaid(\DateTimeImmutable $paidAt): static
    {
        if (BillingDocumentStatus::Cancelled === $this->status) {
            throw new \LogicException('BILLING_DOCUMENT_CANCELLED');
        }

        $this->status = BillingDocumentStatus::Paid;
        $this->paidAt = $paidAt;
        $this->amountPaidTnd = $this->amountTnd;
        $this->amountDueTnd = '0.000';

        return $this;
    }

    public function markOverdue(\DateTimeImmutable $now): static
    {
        if (BillingDocumentStatus::Issued !== $this->status) {
            throw new \LogicException('BILLING_DOCUMENT_NOT_ISSUED');
        }
        if (null !== $this->dueAt && $now <= $this->dueAt) {
            throw new \LogicException('BILLING_DOCUMENT_NOT_DUE');
        }

        $this->status = BillingDocumentStatus::Overdue;

        return $this;
    }

    public function cancel(string $reason, \DateTimeImmutable $cancelledAt): static
    {
        if (BillingDocumentStatus::Paid === $this->status) {
            throw new \LogicException('BILLING_DOCUMENT_ALREADY_PAID');
        }

        $this->status = BillingDocumentStatus::Cancelled;
        $this->cancelledAt = $cancelledAt;
        $this->cancellationReason = $reason;

        return $this;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function getMerchant(): User
    {
        return $this->merchant;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function getDocumentType(): BillingDocumentType
    {
        return $this->documentType;
    }

    public function getStatus(): BillingDocumentStatus
    {
        return $this->status;
    }

    public function getPricingPhase(): SubscriptionPricingPhase
    {
        return $this->pricingPhase;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getBillingPeriodStart(): \DateTimeImmutable
    {
        return $this->billingPeriodStart;
    }

    public function getBillingPeriodEnd(): \DateTimeImmutable
    {
        return $this->billingPeriodEnd;
    }

    public function getIssuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function getAmountTnd(): string
    {
        return bcadd($this->amountTnd, '0', 3);
    }

    public function getAmountPaidTnd(): string
    {
        return bcadd($this->amountPaidTnd, '0', 3);
    }

    public function getAmountDueTnd(): string
    {
        return bcadd($this->amountDueTnd, '0', 3);
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
