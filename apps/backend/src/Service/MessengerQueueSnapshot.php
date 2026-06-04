<?php

declare(strict_types=1);

namespace App\Service;

final readonly class MessengerQueueSnapshot
{
    public function __construct(
        public string $status,
        public int $pending,
        public int $failed,
        public ?int $oldestAgeSeconds,
        public ?\DateTimeImmutable $lastConsumedAt,
        public int $pendingThreshold,
        public int $oldestAgeSecondsThreshold,
        public \DateTimeImmutable $checkedAt,
    ) {
    }
}
