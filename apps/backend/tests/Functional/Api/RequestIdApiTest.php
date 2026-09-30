<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Uid\Uuid;

final class RequestIdApiTest extends FunctionalApiTestCase
{
    public function testPublicGetSuccessResponseCarriesServerGeneratedRequestId(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson('GET', \sprintf('/api/stores/by-qr/%s', $shop->getQrCodeToken()));

        self::assertSame(200, $response->getStatusCode());
        $requestId = $response->headers->get('X-Request-Id');
        self::assertIsString($requestId);
        self::assertTrue(Uuid::isValid($requestId));
    }

    public function testNotFoundErrorResponseCarriesRequestId(): void
    {
        $response = $this->requestJson('GET', '/api/stores/by-qr/unknown-token-request-id-test');

        self::assertSame(404, $response->getStatusCode());
        $requestId = $response->headers->get('X-Request-Id');
        self::assertIsString($requestId);
        self::assertTrue(Uuid::isValid($requestId));
    }

    public function testValidationErrorResponseCarriesRequestId(): void
    {
        $response = $this->requestJson('POST', '/api/auth/register/customer', [
            'email' => 'not-an-email',
            'password' => 'x',
            'name' => '',
        ]);

        self::assertSame(422, $response->getStatusCode());
        $requestId = $response->headers->get('X-Request-Id');
        self::assertIsString($requestId);
        self::assertTrue(Uuid::isValid($requestId));
    }

    public function testValidClientProvidedIdIsEchoedBack(): void
    {
        $shop = $this->createShop();
        $clientRequestId = 'mobile-e2e-0042-ABC';

        $response = $this->requestWithClientRequestId(
            \sprintf('/api/stores/by-qr/%s', $shop->getQrCodeToken()),
            $clientRequestId,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($clientRequestId, $response->headers->get('X-Request-Id'));
    }

    public function testValidClientProvidedIdIsEchoedBackOnErrorResponse(): void
    {
        $clientRequestId = '550e8400-e29b-41d4-a716-446655440000';

        $response = $this->requestWithClientRequestId('/api/stores/by-qr/unknown-token', $clientRequestId);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame($clientRequestId, $response->headers->get('X-Request-Id'));
    }

    public function testInvalidClientProvidedIdIsReplacedByServerGeneratedId(): void
    {
        $shop = $this->createShop();
        $invalidClientRequestId = 'bad id!';

        $response = $this->requestWithClientRequestId(
            \sprintf('/api/stores/by-qr/%s', $shop->getQrCodeToken()),
            $invalidClientRequestId,
        );

        self::assertSame(200, $response->getStatusCode());
        $requestId = $response->headers->get('X-Request-Id');
        self::assertIsString($requestId);
        self::assertNotSame($invalidClientRequestId, $requestId);
        self::assertTrue(Uuid::isValid($requestId));
    }

    public function testOversizedClientProvidedIdIsNeverReflected(): void
    {
        $shop = $this->createShop();
        $oversizedClientRequestId = str_repeat('a', 200);

        $response = $this->requestWithClientRequestId(
            \sprintf('/api/stores/by-qr/%s', $shop->getQrCodeToken()),
            $oversizedClientRequestId,
        );

        self::assertSame(200, $response->getStatusCode());
        $requestId = $response->headers->get('X-Request-Id');
        self::assertIsString($requestId);
        self::assertNotSame($oversizedClientRequestId, $requestId);
        self::assertTrue(Uuid::isValid($requestId));
    }

    private function requestWithClientRequestId(string $path, string $clientRequestId): Response
    {
        $request = Request::create($path, 'GET', server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CLIENT_REQUEST_ID' => $clientRequestId,
        ]);

        return self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
    }
}
