<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final readonly class MessengerQueueMonitor
{
    public function __construct(
        private Connection $connection,
        private int $pendingThreshold = 100,
        private int $oldestAgeSecondsThreshold = 900,
    ) {
    }

    public function snapshot(): MessengerQueueSnapshot
    {
        $checkedAt = new \DateTimeImmutable();
        $pending = $this->countPendingAsyncMessages($checkedAt);
        $failed = $this->countFailedMessages();
        $oldestAgeSeconds = $this->oldestPendingAgeSeconds($checkedAt);
        $lastConsumedAt = $this->lastConsumedAt();

        $isDegraded = $pending > $this->pendingThreshold
            || $failed > 0
            || (null !== $oldestAgeSeconds && $oldestAgeSeconds > $this->oldestAgeSecondsThreshold);

        return new MessengerQueueSnapshot(
            status: $isDegraded ? 'degraded' : 'ok',
            pending: $pending,
            failed: $failed,
            oldestAgeSeconds: $oldestAgeSeconds,
            lastConsumedAt: $lastConsumedAt,
            pendingThreshold: $this->pendingThreshold,
            oldestAgeSecondsThreshold: $this->oldestAgeSecondsThreshold,
            checkedAt: $checkedAt,
        );
    }

    private function countPendingAsyncMessages(\DateTimeImmutable $checkedAt): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'async' AND delivered_at IS NULL AND available_at <= :checkedAt",
            ['checkedAt' => $checkedAt->format('Y-m-d H:i:s')],
        );
    }

    private function countFailedMessages(): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'",
        );
    }

    private function oldestPendingAgeSeconds(\DateTimeImmutable $checkedAt): ?int
    {
        $raw = $this->connection->fetchOne(
            "SELECT MIN(available_at) FROM messenger_messages WHERE queue_name = 'async' AND delivered_at IS NULL AND available_at <= :checkedAt",
            ['checkedAt' => $checkedAt->format('Y-m-d H:i:s')],
        );

        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        $oldest = new \DateTimeImmutable($raw);

        return max(0, $checkedAt->getTimestamp() - $oldest->getTimestamp());
    }

    private function lastConsumedAt(): ?\DateTimeImmutable
    {
        if (!$this->hasWorkerStateTable()) {
            return null;
        }

        $raw = $this->connection->fetchOne(
            "SELECT MAX(last_consumed_at) FROM messenger_worker_state WHERE queue_name = 'async'",
        );

        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        return new \DateTimeImmutable($raw);
    }

    private function hasWorkerStateTable(): bool
    {
        return $this->connection->createSchemaManager()->tablesExist(['messenger_worker_state']);
    }
}
