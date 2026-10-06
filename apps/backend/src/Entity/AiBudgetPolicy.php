<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AiBudgetPolicyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Admin-configured spending guardrails for the AI catalog photo import pilot
 * (CATALOG-AI-007 / issue #644), configuration slice only. No usage ledger,
 * reservation or dispatch logic reads this yet — those depend on #638
 * (merchant quota), #640 (provider adapters) and #641 (dispatch orchestration),
 * none of which exist yet. `enabled: false` by default means "absence de
 * configuration requise = pas de départ payant".
 */
#[ORM\Entity(repositoryClass: AiBudgetPolicyRepository::class)]
#[ORM\Table(name: 'ai_budget_policies')]
#[ORM\UniqueConstraint(name: 'UNIQ_AI_BUDGET_POLICY_SINGLETON', columns: ['singleton_key'])]
#[ORM\HasLifecycleCallbacks]
class AiBudgetPolicy
{
    public const DEFAULT_ID = '00000000-0000-0000-0000-000000000005';
    public const DEFAULT_SINGLETON_KEY = 'default';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 32)]
    private string $singletonKey = self::DEFAULT_SINGLETON_KEY;

    #[ORM\Column]
    private bool $enabled = false;

    #[ORM\Column]
    private bool $openaiEnabled = false;

    #[ORM\Column]
    private bool $geminiEnabled = false;

    #[ORM\Column]
    private bool $mistralEnabled = false;

    #[ORM\Column]
    private bool $qwenEnabled = false;

    #[ORM\Column(length: 3)]
    private string $currency = 'USD';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: true)]
    private ?string $maxCostPerCallAmount = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: true)]
    private ?string $maxCostPerPhotoAmount = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: true)]
    private ?string $maxCostPerShopAmount = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: true)]
    private ?string $globalPeriodCapAmount = null;

    #[ORM\Column]
    private int $globalPeriodDays = 30;

    #[ORM\Column(nullable: true)]
    private ?int $maxOutputTokensPerCall = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxCropsPerPhoto = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxAttemptsPerPhoto = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxCallDurationSeconds = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxConcurrentCalls = null;

    #[ORM\Column]
    private int $alertThresholdPercent = 80;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
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

    public function getSingletonKey(): string
    {
        return $this->singletonKey;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isOpenaiEnabled(): bool
    {
        return $this->openaiEnabled;
    }

    public function setOpenaiEnabled(bool $openaiEnabled): static
    {
        $this->openaiEnabled = $openaiEnabled;

        return $this;
    }

    public function isGeminiEnabled(): bool
    {
        return $this->geminiEnabled;
    }

    public function setGeminiEnabled(bool $geminiEnabled): static
    {
        $this->geminiEnabled = $geminiEnabled;

        return $this;
    }

    public function isMistralEnabled(): bool
    {
        return $this->mistralEnabled;
    }

    public function setMistralEnabled(bool $mistralEnabled): static
    {
        $this->mistralEnabled = $mistralEnabled;

        return $this;
    }

    public function isQwenEnabled(): bool
    {
        return $this->qwenEnabled;
    }

    public function setQwenEnabled(bool $qwenEnabled): static
    {
        $this->qwenEnabled = $qwenEnabled;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getMaxCostPerCallAmount(): ?string
    {
        return $this->maxCostPerCallAmount;
    }

    public function setMaxCostPerCallAmount(?string $maxCostPerCallAmount): static
    {
        $this->maxCostPerCallAmount = $maxCostPerCallAmount;

        return $this;
    }

    public function getMaxCostPerPhotoAmount(): ?string
    {
        return $this->maxCostPerPhotoAmount;
    }

    public function setMaxCostPerPhotoAmount(?string $maxCostPerPhotoAmount): static
    {
        $this->maxCostPerPhotoAmount = $maxCostPerPhotoAmount;

        return $this;
    }

    public function getMaxCostPerShopAmount(): ?string
    {
        return $this->maxCostPerShopAmount;
    }

    public function setMaxCostPerShopAmount(?string $maxCostPerShopAmount): static
    {
        $this->maxCostPerShopAmount = $maxCostPerShopAmount;

        return $this;
    }

    public function getGlobalPeriodCapAmount(): ?string
    {
        return $this->globalPeriodCapAmount;
    }

    public function setGlobalPeriodCapAmount(?string $globalPeriodCapAmount): static
    {
        $this->globalPeriodCapAmount = $globalPeriodCapAmount;

        return $this;
    }

    public function getGlobalPeriodDays(): int
    {
        return $this->globalPeriodDays;
    }

    public function setGlobalPeriodDays(int $globalPeriodDays): static
    {
        $this->globalPeriodDays = $globalPeriodDays;

        return $this;
    }

    public function getMaxOutputTokensPerCall(): ?int
    {
        return $this->maxOutputTokensPerCall;
    }

    public function setMaxOutputTokensPerCall(?int $maxOutputTokensPerCall): static
    {
        $this->maxOutputTokensPerCall = $maxOutputTokensPerCall;

        return $this;
    }

    public function getMaxCropsPerPhoto(): ?int
    {
        return $this->maxCropsPerPhoto;
    }

    public function setMaxCropsPerPhoto(?int $maxCropsPerPhoto): static
    {
        $this->maxCropsPerPhoto = $maxCropsPerPhoto;

        return $this;
    }

    public function getMaxAttemptsPerPhoto(): ?int
    {
        return $this->maxAttemptsPerPhoto;
    }

    public function setMaxAttemptsPerPhoto(?int $maxAttemptsPerPhoto): static
    {
        $this->maxAttemptsPerPhoto = $maxAttemptsPerPhoto;

        return $this;
    }

    public function getMaxCallDurationSeconds(): ?int
    {
        return $this->maxCallDurationSeconds;
    }

    public function setMaxCallDurationSeconds(?int $maxCallDurationSeconds): static
    {
        $this->maxCallDurationSeconds = $maxCallDurationSeconds;

        return $this;
    }

    public function getMaxConcurrentCalls(): ?int
    {
        return $this->maxConcurrentCalls;
    }

    public function setMaxConcurrentCalls(?int $maxConcurrentCalls): static
    {
        $this->maxConcurrentCalls = $maxConcurrentCalls;

        return $this;
    }

    public function getAlertThresholdPercent(): int
    {
        return $this->alertThresholdPercent;
    }

    public function setAlertThresholdPercent(int $alertThresholdPercent): static
    {
        $this->alertThresholdPercent = $alertThresholdPercent;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
