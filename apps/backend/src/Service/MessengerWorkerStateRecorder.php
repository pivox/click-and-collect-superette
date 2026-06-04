<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final readonly class MessengerWorkerStateRecorder
{
    public function __construct(private Connection $connection)
    {
    }

    public function recordConsumedMessage(string $queueName, \DateTimeImmutable $consumedAt): void
    {
        $this->connection->transactional(function () use ($queueName, $consumedAt): void {
            $this->connection->delete('messenger_worker_state', ['queue_name' => $queueName]);
            $this->connection->insert('messenger_worker_state', [
                'queue_name' => $queueName,
                'last_consumed_at' => $consumedAt->format('Y-m-d H:i:s'),
                'updated_at' => $consumedAt->format('Y-m-d H:i:s'),
            ]);
        });
    }
}
