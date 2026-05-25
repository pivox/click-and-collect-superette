<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Symfony\Component\HttpFoundation\Request;

final class ApiDocsExposureTest extends FunctionalApiTestCase
{
    public function testOpenApiJsonIsPubliclyAccessible(): void
    {
        $request = Request::create('/api/docs.json', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = self::$kernel->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');

        $payload = json_decode($response->getContent() ?: '', true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('paths', $payload);
    }

    public function testSwaggerUiHtmlIsPubliclyAccessible(): void
    {
        $request = Request::create('/api/docs.html', 'GET', server: ['HTTP_ACCEPT' => 'text/html']);
        $response = self::$kernel->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->headers->get('Content-Type') ?? '');
    }
}
