<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductAiEnrichmentJob;
use App\Enum\ProductAiEnrichmentJobStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProductAiEnrichmentRunner
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductAiEnrichmentPlanner $planner,
        private ProductAiEnrichmentPayloadFactory $payloadFactory,
        private ProductAiEnrichmentOpenAiClient $openAiClient,
        private ProductAiEnrichmentResultApplier $resultApplier,
    ) {
    }

    public function run(string $apiKey, string $model, int $limit, int $batchSize, int $maxActiveBatches): ProductAiEnrichmentRunResult
    {
        $plan = $this->planner->planMissingProductJobs($limit);

        if ('' === trim($apiKey)) {
            return new ProductAiEnrichmentRunResult($plan->createdJobs, 0, 0, 0, 0, true);
        }

        $activeBatchesChecked = $this->processSubmittedBatches($apiKey);
        $jobsSubmitted = 0;

        if ($this->countActiveBatches() < max(1, $maxActiveBatches)) {
            $jobsSubmitted = $this->submitPendingBatch($apiKey, $model, $batchSize);
        }

        $this->entityManager->flush();

        return new ProductAiEnrichmentRunResult(
            jobsCreated: $plan->createdJobs,
            jobsSubmitted: $jobsSubmitted,
            jobsApplied: $this->countStatus(ProductAiEnrichmentJobStatus::Applied),
            jobsFailed: $this->countStatus(ProductAiEnrichmentJobStatus::Failed),
            activeBatchesChecked: $activeBatchesChecked,
            openAiSkipped: false,
        );
    }

    private function submitPendingBatch(string $apiKey, string $model, int $batchSize): int
    {
        $jobs = $this->entityManager->getRepository(ProductAiEnrichmentJob::class)->findBy(
            ['status' => ProductAiEnrichmentJobStatus::Pending],
            ['createdAt' => 'ASC'],
            max(1, $batchSize),
        );

        if ([] === $jobs) {
            return 0;
        }

        $requests = [];
        foreach ($jobs as $job) {
            $requests[] = json_encode($this->payloadFactory->buildBatchRequest($job, $model), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        }

        $batchId = $this->openAiClient->createBatch($apiKey, implode("\n", $requests)."\n");

        foreach ($jobs as $job) {
            $job->markSubmitted($batchId);
        }

        return \count($jobs);
    }

    private function processSubmittedBatches(string $apiKey): int
    {
        $jobsByBatch = $this->submittedJobsByBatch();
        $checked = 0;

        foreach ($jobsByBatch as $batchId => $jobs) {
            ++$checked;
            $batch = $this->openAiClient->retrieveBatch($apiKey, $batchId);
            $status = (string) ($batch['status'] ?? '');

            if ('completed' === $status && \is_string($batch['output_file_id'] ?? null)) {
                $this->applyOutputLines($apiKey, $batch['output_file_id'], $jobs);
                continue;
            }

            if (\in_array($status, ['failed', 'expired', 'cancelled'], true)) {
                foreach ($jobs as $job) {
                    $job->markFailed('OPENAI_BATCH_'.$status);
                }
            }
        }

        return $checked;
    }

    /**
     * @param list<ProductAiEnrichmentJob> $jobs
     */
    private function applyOutputLines(string $apiKey, string $fileId, array $jobs): void
    {
        $jobsById = [];
        foreach ($jobs as $job) {
            $jobsById[$job->getId()->toRfc4122()] = $job;
        }

        foreach ($this->openAiClient->downloadOutputLines($apiKey, $fileId) as $line) {
            $customId = (string) ($line['custom_id'] ?? '');
            $job = $jobsById[$customId] ?? null;
            if (!$job instanceof ProductAiEnrichmentJob) {
                continue;
            }

            try {
                $resultPayload = $this->extractResultPayload($line);
                $this->resultApplier->apply($job, ProductAiEnrichmentResult::fromPayload($resultPayload));
            } catch (\Throwable $exception) {
                $job->markFailed($exception->getMessage());
            }
        }
    }

    /**
     * @param array<string, mixed> $line
     *
     * @return array<string, mixed>
     */
    private function extractResultPayload(array $line): array
    {
        $body = $line['response']['body'] ?? null;
        if (!\is_array($body)) {
            throw new \RuntimeException('OPENAI_RESPONSE_BODY_MISSING');
        }

        $text = $body['output_text'] ?? null;
        if (!\is_string($text)) {
            $text = $body['output'][0]['content'][0]['text'] ?? null;
        }

        if (!\is_string($text)) {
            throw new \RuntimeException('OPENAI_RESPONSE_TEXT_MISSING');
        }

        $payload = json_decode($text, true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($payload)) {
            throw new \RuntimeException('OPENAI_RESPONSE_JSON_INVALID');
        }

        /* @var array<string, mixed> $payload */
        return $payload;
    }

    /** @return array<string, list<ProductAiEnrichmentJob>> */
    private function submittedJobsByBatch(): array
    {
        $jobs = $this->entityManager->getRepository(ProductAiEnrichmentJob::class)->findBy(['status' => ProductAiEnrichmentJobStatus::Submitted]);
        $grouped = [];

        foreach ($jobs as $job) {
            $batchId = $job->getOpenaiBatchId();
            if (null === $batchId) {
                continue;
            }

            $grouped[$batchId] ??= [];
            $grouped[$batchId][] = $job;
        }

        return $grouped;
    }

    private function countActiveBatches(): int
    {
        return \count($this->submittedJobsByBatch());
    }

    private function countStatus(ProductAiEnrichmentJobStatus $status): int
    {
        return (int) $this->entityManager->createQuery(
            'SELECT COUNT(j.id) FROM App\Entity\ProductAiEnrichmentJob j WHERE j.status = :status'
        )
            ->setParameter('status', $status->value)
            ->getSingleScalarResult();
    }
}
