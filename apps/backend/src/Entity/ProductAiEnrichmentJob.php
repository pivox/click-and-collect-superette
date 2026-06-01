<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProductAiEnrichmentJobStatus;
use App\Repository\ProductAiEnrichmentJobRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProductAiEnrichmentJobRepository::class)]
#[ORM\Table(name: 'product_ai_enrichment_jobs')]
#[ORM\Index(name: 'IDX_PRODUCT_AI_ENRICHMENT_STATUS', columns: ['status'])]
#[ORM\Index(name: 'IDX_PRODUCT_AI_ENRICHMENT_BATCH', columns: ['openai_batch_id'])]
#[ORM\Index(name: 'IDX_PRODUCT_AI_ENRICHMENT_PRODUCT', columns: ['product_reference_id'])]
#[ORM\HasLifecycleCallbacks]
class ProductAiEnrichmentJob
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: ProductReference::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProductReference $productReference;

    #[ORM\Column(length: 32, enumType: ProductAiEnrichmentJobStatus::class)]
    private ProductAiEnrichmentJobStatus $status = ProductAiEnrichmentJobStatus::Pending;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $openaiBatchId = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $openaiOutputFileId = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $inputPayload = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $responsePayload = null;

    #[ORM\Column(type: 'integer')]
    private int $attemptCount = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 6, nullable: true)]
    private ?string $estimatedCostUsd = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $appliedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(ProductReference $productReference)
    {
        $this->id = Uuid::v4();
        $this->productReference = $productReference;
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

    public function getProductReference(): ProductReference
    {
        return $this->productReference;
    }

    public function getStatus(): ProductAiEnrichmentJobStatus
    {
        return $this->status;
    }

    public function isApplied(): bool
    {
        return ProductAiEnrichmentJobStatus::Applied === $this->status;
    }

    public function getOpenaiBatchId(): ?string
    {
        return $this->openaiBatchId;
    }

    public function getOpenaiOutputFileId(): ?string
    {
        return $this->openaiOutputFileId;
    }

    /** @return array<string, mixed>|null */
    public function getInputPayload(): ?array
    {
        return $this->inputPayload;
    }

    /** @param array<string, mixed> $inputPayload */
    public function setInputPayload(array $inputPayload): static
    {
        $this->inputPayload = $inputPayload;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getResponsePayload(): ?array
    {
        return $this->responsePayload;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function getEstimatedCostUsd(): ?string
    {
        return $this->estimatedCostUsd;
    }

    public function setEstimatedCostUsd(?string $estimatedCostUsd): static
    {
        $this->estimatedCostUsd = null === $estimatedCostUsd ? null : bcadd($estimatedCostUsd, '0', 6);

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getAppliedAt(): ?\DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function markSubmitted(string $openaiBatchId, ?\DateTimeImmutable $submittedAt = null): void
    {
        $this->status = ProductAiEnrichmentJobStatus::Submitted;
        $this->openaiBatchId = $openaiBatchId;
        $this->submittedAt = $submittedAt ?? new \DateTimeImmutable();
        ++$this->attemptCount;
        $this->errorMessage = null;
    }

    /**
     * @param array<string, mixed> $responsePayload
     */
    public function markSucceeded(array $responsePayload, ?string $outputFileId = null, ?\DateTimeImmutable $completedAt = null): void
    {
        $this->status = ProductAiEnrichmentJobStatus::Succeeded;
        $this->responsePayload = $responsePayload;
        $this->openaiOutputFileId = $outputFileId;
        $this->completedAt = $completedAt ?? new \DateTimeImmutable();
        $this->errorMessage = null;
    }

    public function markApplied(?\DateTimeImmutable $appliedAt = null): void
    {
        $this->status = ProductAiEnrichmentJobStatus::Applied;
        $this->appliedAt = $appliedAt ?? new \DateTimeImmutable();
    }

    public function markFailed(string $errorMessage): void
    {
        $this->status = ProductAiEnrichmentJobStatus::Failed;
        $this->errorMessage = mb_substr($errorMessage, 0, 1000);
        $this->completedAt = new \DateTimeImmutable();
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
