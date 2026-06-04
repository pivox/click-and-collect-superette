<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Service\MessengerWorkerStateRecorder;
use App\Tests\Functional\Api\FunctionalApiTestCase;

final class MessengerWorkerStateRecorderTest extends FunctionalApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager->getConnection()->executeStatement('DROP TABLE IF EXISTS messenger_worker_state');
        $this->entityManager->getConnection()->executeStatement('CREATE TABLE messenger_worker_state (
            queue_name VARCHAR(190) NOT NULL PRIMARY KEY,
            last_consumed_at DATETIME DEFAULT NULL,
            updated_at DATETIME NOT NULL
        )');
    }

    public function testRecorderStoresAndReplacesLastConsumedAtForQueue(): void
    {
        $recorder = self::getContainer()->get(MessengerWorkerStateRecorder::class);
        self::assertInstanceOf(MessengerWorkerStateRecorder::class, $recorder);

        $recorder->recordConsumedMessage('async', new \DateTimeImmutable('2026-06-04 10:00:00'));
        $recorder->recordConsumedMessage('async', new \DateTimeImmutable('2026-06-04 10:05:00'));

        $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT * FROM messenger_worker_state');

        self::assertCount(1, $rows);
        self::assertSame('async', $rows[0]['queue_name']);
        self::assertSame('2026-06-04 10:05:00', $rows[0]['last_consumed_at']);
        self::assertSame('2026-06-04 10:05:00', $rows[0]['updated_at']);
    }
}
