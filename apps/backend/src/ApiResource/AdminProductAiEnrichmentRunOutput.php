<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\AdminRunProductAiEnrichmentInput;
use App\Processor\AdminRunProductAiEnrichmentProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/product-ai-enrichment/run',
            formats: ['json' => ['application/json']],
            input: AdminRunProductAiEnrichmentInput::class,
            output: self::class,
            normalizationContext: ['groups' => ['admin_product_ai_enrichment:read']],
            processor: AdminRunProductAiEnrichmentProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            status: 200,
            validate: true,
        ),
    ],
)]
final readonly class AdminProductAiEnrichmentRunOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_product_ai_enrichment:read'])]
        #[SerializedName('jobs_created')]
        public int $jobsCreated,
        #[Groups(['admin_product_ai_enrichment:read'])]
        #[SerializedName('jobs_submitted')]
        public int $jobsSubmitted,
        #[Groups(['admin_product_ai_enrichment:read'])]
        #[SerializedName('jobs_applied_total')]
        public int $jobsAppliedTotal,
        #[Groups(['admin_product_ai_enrichment:read'])]
        #[SerializedName('jobs_failed_total')]
        public int $jobsFailedTotal,
        #[Groups(['admin_product_ai_enrichment:read'])]
        #[SerializedName('active_batches_checked')]
        public int $activeBatchesChecked,
        #[Groups(['admin_product_ai_enrichment:read'])]
        #[SerializedName('openai_skipped')]
        public bool $openAiSkipped,
    ) {
    }
}
