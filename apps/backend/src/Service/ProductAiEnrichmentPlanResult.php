<?php

declare(strict_types=1);

namespace App\Service;

final readonly class ProductAiEnrichmentPlanResult
{
    public function __construct(
        public int $scannedProducts,
        public int $createdJobs,
    ) {
    }
}
