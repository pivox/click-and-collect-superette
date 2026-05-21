<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Symfony\Component\HttpFoundation\Response;

final class HealthCheckApiTest extends FunctionalApiTestCase
{
    public function testHealthCheckIsPublicAndReturnsOkStatus(): void
    {
        $response = $this->requestJson('GET', '/api/health');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('ok', $payload['status']);
        self::assertArrayHasKey('timestamp', $payload);
        self::assertIsString($payload['timestamp']);
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $payload['timestamp']));
    }

    public function testHealthCheckDoesNotExposeCriticalEnvironmentNames(): void
    {
        $response = $this->requestJson('GET', '/api/health');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringNotContainsString('APP_SECRET', $content);
        self::assertStringNotContainsString('DATABASE_URL', $content);
        self::assertStringNotContainsString('JWT_SECRET_KEY', $content);
    }
}
