<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\HealthCheckController;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class HealthCheckControllerTest extends TestCase
{
    public function testReturnsServiceUnavailableWhenDatabaseConnectionFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willThrowException(new \RuntimeException('database unavailable'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getConnection')
            ->willReturn($connection);

        $response = (new HealthCheckController($entityManager))();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());

        $content = $response->getContent();
        self::assertIsString($content);

        $payload = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame('error', $payload['status']);
        self::assertSame(['database' => 'error'], $payload['checks']);
        self::assertArrayHasKey('timestamp', $payload);
        self::assertStringNotContainsString('database unavailable', $content);
    }
}
