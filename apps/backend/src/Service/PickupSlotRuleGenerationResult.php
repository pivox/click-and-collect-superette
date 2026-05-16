<?php

declare(strict_types=1);

namespace App\Service;

final readonly class PickupSlotRuleGenerationResult
{
    public function __construct(
        public int $generatedCount,
        public int $skippedExistingCount,
        public int $skippedClosureCount,
        public \DateTimeImmutable $horizonStart,
        public \DateTimeImmutable $horizonEnd,
    ) {
    }
}
