<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminProductAiEnrichmentRunOutput;
use App\Dto\AdminRunProductAiEnrichmentInput;
use App\Service\AdminAuditLogger;
use App\Service\ProductAiEnrichmentRunner;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<AdminRunProductAiEnrichmentInput, AdminProductAiEnrichmentRunOutput>
 */
final readonly class AdminRunProductAiEnrichmentProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductAiEnrichmentRunner $runner,
        private AdminAuditLogger $auditLogger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminProductAiEnrichmentRunOutput
    {
        if (!$data instanceof AdminRunProductAiEnrichmentInput) {
            throw new \InvalidArgumentException('ADMIN_PRODUCT_AI_ENRICHMENT_INVALID_INPUT');
        }

        $result = $this->runner->run(
            apiKey: $this->env('OPENAI_API_KEY') ?? '',
            model: $this->env('OPENAI_PRODUCT_ENRICHMENT_MODEL') ?? 'gpt-4o-mini',
            limit: $data->limit,
            batchSize: $data->limit,
            maxActiveBatches: max(1, (int) ($this->env('OPENAI_PRODUCT_MAX_ACTIVE_BATCHES') ?? '1')),
        );

        $this->auditLogger->log(
            action: 'product_ai_enrichment.run',
            resourceType: 'product_ai_enrichment',
            resourceId: 'admin-product-ai-enrichment-run',
            summary: \sprintf('Recherche IA lancée pour %d produits du référentiel.', $data->limit),
            metadata: [
                'limit' => $data->limit,
                'jobs_created' => $result->jobsCreated,
                'jobs_submitted' => $result->jobsSubmitted,
                'openai_skipped' => $result->openAiSkipped,
            ],
        );
        $this->entityManager->flush();

        return new AdminProductAiEnrichmentRunOutput(
            id: 'admin-product-ai-enrichment-run',
            jobsCreated: $result->jobsCreated,
            jobsSubmitted: $result->jobsSubmitted,
            jobsAppliedTotal: $result->jobsApplied,
            jobsFailedTotal: $result->jobsFailed,
            activeBatchesChecked: $result->activeBatchesChecked,
            openAiSkipped: $result->openAiSkipped,
        );
    }

    private function env(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if (false === $value || '' === trim((string) $value)) {
            return null;
        }

        return (string) $value;
    }
}
