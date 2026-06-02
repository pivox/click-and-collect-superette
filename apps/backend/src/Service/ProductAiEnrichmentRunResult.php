<?php

declare(strict_types=1);

namespace App\Service;

final readonly class ProductAiEnrichmentRunResult
{
    public function __construct(
        public int $jobsCreated,
        public int $jobsSubmitted,
        public int $jobsApplied,
        public int $jobsFailed,
        public int $activeBatchesChecked,
        public bool $openAiSkipped,
    ) {
    }
}
