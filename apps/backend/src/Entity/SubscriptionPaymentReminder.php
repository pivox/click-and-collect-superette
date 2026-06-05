<?php

declare(strict_types=1);

namespace App\Entity;

use App\Billing\PaymentReminderStage;
use App\Enum\SubscriptionPaymentReminderChannel;
use App\Enum\SubscriptionPaymentReminderStatus;
use App\Repository\SubscriptionPaymentReminderRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SubscriptionPaymentReminderRepository::class)]
#[ORM\Table(name: 'subscription_payment_reminders')]
#[ORM\UniqueConstraint(name: 'UNIQ_SUBSCRIPTION_PAYMENT_REMINDER_STAGE_CHANNEL', columns: ['billing_document_id', 'stage', 'channel'])]
#[ORM\Index(name: 'IDX_SUBSCRIPTION_PAYMENT_REMINDER_DOCUMENT', columns: ['billing_document_id'])]
#[ORM\Index(name: 'IDX_SUBSCRIPTION_PAYMENT_REMINDER_MERCHANT', columns: ['merchant_id'])]
#[ORM\Index(name: 'IDX_SUBSCRIPTION_PAYMENT_REMINDER_STATUS', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class SubscriptionPaymentReminder
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: BillingDocument::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BillingDocument $billingDocument;

    #[ORM\ManyToOne(targetEntity: Subscription::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Subscription $subscription;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $merchant;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdByAdmin = null;

    #[ORM\Column(length: 30)]
    private string $stage;

    #[ORM\Column(length: 20, enumType: SubscriptionPaymentReminderChannel::class)]
    private SubscriptionPaymentReminderChannel $channel;

    #[ORM\Column(length: 20, enumType: SubscriptionPaymentReminderStatus::class)]
    private SubscriptionPaymentReminderStatus $status;

    #[ORM\Column(name: 'scheduled_at')]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(name: 'sent_at', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(name: 'failed_at', nullable: true)]
    private ?\DateTimeImmutable $failedAt = null;

    #[ORM\Column(name: 'failure_reason', type: 'text', nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(BillingDocument $document, string $stage, SubscriptionPaymentReminderChannel $channel)
    {
        $now = new \DateTimeImmutable();
        $this->id = Uuid::v4();
        $this->billingDocument = $document;
        $this->subscription = $document->getSubscription();
        $this->merchant = $document->getMerchant();
        $this->stage = $stage;
        $this->channel = $channel;
        $this->scheduledAt = $document->getDueAt() ?? $now;
        $this->status = SubscriptionPaymentReminderStatus::Sent;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public static function emailSent(BillingDocument $document, PaymentReminderStage $stage, \DateTimeImmutable $sentAt): self
    {
        $reminder = new self($document, $stage->value, SubscriptionPaymentReminderChannel::Email);
        $reminder->scheduledAt = $sentAt;
        $reminder->status = SubscriptionPaymentReminderStatus::Sent;
        $reminder->sentAt = $sentAt;

        return $reminder;
    }

    public static function emailFailed(
        BillingDocument $document,
        PaymentReminderStage $stage,
        \DateTimeImmutable $failedAt,
        string $reason,
    ): self {
        $reminder = new self($document, $stage->value, SubscriptionPaymentReminderChannel::Email);
        $reminder->scheduledAt = $failedAt;
        $reminder->status = SubscriptionPaymentReminderStatus::Failed;
        $reminder->failedAt = $failedAt;
        $reminder->failureReason = mb_substr($reason, 0, 500);

        return $reminder;
    }

    public static function whatsappContacted(
        BillingDocument $document,
        string $stage,
        User $admin,
        \DateTimeImmutable $contactedAt,
    ): self {
        $reminder = new self($document, $stage, SubscriptionPaymentReminderChannel::WhatsappManual);
        $reminder->status = SubscriptionPaymentReminderStatus::Contacted;
        $reminder->scheduledAt = $contactedAt;
        $reminder->sentAt = $contactedAt;
        $reminder->createdByAdmin = $admin;

        return $reminder;
    }

    public function markEmailSent(\DateTimeImmutable $sentAt): static
    {
        $this->status = SubscriptionPaymentReminderStatus::Sent;
        $this->sentAt = $sentAt;
        $this->failedAt = null;
        $this->failureReason = null;

        return $this;
    }

    public function markEmailFailed(\DateTimeImmutable $failedAt, string $reason): static
    {
        $this->status = SubscriptionPaymentReminderStatus::Failed;
        $this->failedAt = $failedAt;
        $this->failureReason = mb_substr($reason, 0, 500);

        return $this;
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

    public function getCreatedByAdmin(): ?User
    {
        return $this->createdByAdmin;
    }

    public function getStage(): string
    {
        return $this->stage;
    }

    public function getChannel(): SubscriptionPaymentReminderChannel
    {
        return $this->channel;
    }

    public function getStatus(): SubscriptionPaymentReminderStatus
    {
        return $this->status;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getFailedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
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
