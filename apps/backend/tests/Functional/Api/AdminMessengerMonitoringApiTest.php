<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Symfony\Component\HttpFoundation\Response;

final class AdminMessengerMonitoringApiTest extends FunctionalApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createMessengerMonitoringTables();
    }

    public function testAdminSeesHealthyEmptyMessengerQueue(): void
    {
        $admin = $this->createUser('admin-messenger-empty@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/ops/messenger', user: $admin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('ok', $payload['status']);
        self::assertSame(0, $payload['pending']);
        self::assertSame(0, $payload['failed']);
        self::assertNull($payload['oldest_age_s']);
        self::assertNull($payload['last_consumed_at']);
        self::assertSame(100, $payload['thresholds']['pending']);
        self::assertSame(900, $payload['thresholds']['oldest_age_s']);
        self::assertArrayHasKey('checked_at', $payload);
    }

    public function testAdminSeesDegradedStatusWhenFailedMessagesExist(): void
    {
        $admin = $this->createUser('admin-messenger-failed@example.test', ['ROLE_ADMIN']);
        $this->insertMessengerMessage('failed', '-20 minutes', '-20 minutes');
        $this->insertWorkerState('-5 minutes');

        $response = $this->requestJson('GET', '/api/admin/ops/messenger', user: $admin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('degraded', $payload['status']);
        self::assertSame(0, $payload['pending']);
        self::assertSame(1, $payload['failed']);
        self::assertNull($payload['oldest_age_s']);
        self::assertNotNull($payload['last_consumed_at']);
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $payload['last_consumed_at']));
    }

    public function testAdminSeesDegradedStatusWhenOldestPendingMessageIsTooOld(): void
    {
        $admin = $this->createUser('admin-messenger-oldest@example.test', ['ROLE_ADMIN']);
        $this->insertMessengerMessage('async', '-30 minutes', '-30 minutes');

        $response = $this->requestJson('GET', '/api/admin/ops/messenger', user: $admin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('degraded', $payload['status']);
        self::assertSame(1, $payload['pending']);
        self::assertSame(0, $payload['failed']);
        self::assertGreaterThanOrEqual(1800, $payload['oldest_age_s']);
    }

    public function testAdminDoesNotCountDelayedAsyncMessagesAsPendingBacklog(): void
    {
        $admin = $this->createUser('admin-messenger-delayed@example.test', ['ROLE_ADMIN']);
        for ($i = 0; $i < 101; ++$i) {
            $this->insertMessengerMessage('async', 'now', '+2 hours');
        }

        $response = $this->requestJson('GET', '/api/admin/ops/messenger', user: $admin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('ok', $payload['status']);
        self::assertSame(0, $payload['pending']);
        self::assertNull($payload['oldest_age_s']);
    }

    public function testAnonymousCannotSeeMessengerMonitoring(): void
    {
        $response = $this->requestJson('GET', '/api/admin/ops/messenger');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testMerchantCannotSeeMessengerMonitoring(): void
    {
        $merchant = $this->createUser('merchant-messenger-monitoring@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('GET', '/api/admin/ops/messenger', user: $merchant);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    private function createMessengerMonitoringTables(): void
    {
        $this->entityManager->getConnection()->executeStatement('DROP TABLE IF EXISTS messenger_worker_state');
        $this->entityManager->getConnection()->executeStatement('DROP TABLE IF EXISTS messenger_messages');

        $this->entityManager->getConnection()->executeStatement('CREATE TABLE messenger_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            body TEXT NOT NULL,
            headers TEXT NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            available_at DATETIME NOT NULL,
            delivered_at DATETIME DEFAULT NULL
        )');
        $this->entityManager->getConnection()->executeStatement('CREATE TABLE messenger_worker_state (
            queue_name VARCHAR(190) NOT NULL PRIMARY KEY,
            last_consumed_at DATETIME DEFAULT NULL,
            updated_at DATETIME NOT NULL
        )');
    }

    private function insertMessengerMessage(string $queueName, string $createdAtModifier, string $availableAtModifier): void
    {
        $now = new \DateTimeImmutable('now');

        $this->entityManager->getConnection()->insert('messenger_messages', [
            'body' => '{}',
            'headers' => '{}',
            'queue_name' => $queueName,
            'created_at' => $now->modify($createdAtModifier)->format('Y-m-d H:i:s'),
            'available_at' => $now->modify($availableAtModifier)->format('Y-m-d H:i:s'),
            'delivered_at' => null,
        ]);
    }

    private function insertWorkerState(string $lastConsumedAtModifier): void
    {
        $now = new \DateTimeImmutable('now');
        $lastConsumedAt = $now->modify($lastConsumedAtModifier);

        $this->entityManager->getConnection()->insert('messenger_worker_state', [
            'queue_name' => 'async',
            'last_consumed_at' => $lastConsumedAt->format('Y-m-d H:i:s'),
            'updated_at' => $lastConsumedAt->format('Y-m-d H:i:s'),
        ]);
    }
}
