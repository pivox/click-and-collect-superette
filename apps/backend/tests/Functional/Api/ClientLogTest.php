<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

final class ClientLogTest extends FunctionalApiTestCase
{
    // --- Access control ---

    public function testAnonymousCanPostLog(): void
    {
        $response = $this->requestJson('POST', '/api/client-logs', [
            'level' => 'error',
            'event' => 'order_creation_failed',
            'message' => 'POST /api/me/orders returned 500',
            'context' => ['route' => '/checkout', 'statusCode' => 500],
            'appVersion' => '0.1.0',
            'environment' => 'production',
            'url' => '/checkout',
        ]);

        self::assertSame(204, $response->getStatusCode());
    }

    // --- Valid payloads ---

    public function testAcceptsWarningLevel(): void
    {
        $response = $this->requestJson('POST', '/api/client-logs', [
            'level' => 'warning',
            'event' => 'checkout_slot_unavailable',
            'message' => 'Slot became unavailable during checkout',
        ]);

        self::assertSame(204, $response->getStatusCode());
    }

    public function testAcceptsAllLevels(): void
    {
        foreach (['debug', 'info', 'warning', 'error'] as $level) {
            $response = $this->requestJson('POST', '/api/client-logs', [
                'level' => $level,
                'event' => 'test_event',
                'message' => 'Test message for level '.$level,
            ]);
            self::assertSame(204, $response->getStatusCode(), "Level '$level' should be accepted.");
        }
    }

    public function testAcceptsOptionalContextFields(): void
    {
        $response = $this->requestJson('POST', '/api/client-logs', [
            'level' => 'error',
            'event' => 'merchant_scan_qr_failed',
            'message' => 'QR code scan failed',
            'context' => [
                'route' => '/merchant/pickup',
                'userRole' => 'merchant',
                'merchantId' => 'mer-123',
                'orderId' => 'ord-456',
                'requestId' => 'req-abc',
                'statusCode' => 404,
                'durationMs' => 342,
            ],
            'appVersion' => '0.1.0',
            'environment' => 'production',
            'url' => '/merchant/pickup',
            'createdAt' => '2026-05-30T12:00:00.000Z',
        ]);

        self::assertSame(204, $response->getStatusCode());
    }

    // --- Validation ---

    public function testRejectsInvalidLevel(): void
    {
        $response = $this->requestJson('POST', '/api/client-logs', [
            'level' => 'critical',
            'event' => 'some_event',
            'message' => 'Some message',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testRejectsBlankEvent(): void
    {
        $response = $this->requestJson('POST', '/api/client-logs', [
            'level' => 'error',
            'event' => '',
            'message' => 'Some message',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testRejectsBlankMessage(): void
    {
        $response = $this->requestJson('POST', '/api/client-logs', [
            'level' => 'error',
            'event' => 'some_event',
            'message' => '',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testRejectsMissingLevel(): void
    {
        $response = $this->requestJson('POST', '/api/client-logs', [
            'event' => 'some_event',
            'message' => 'Some message',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testGetIsNotAllowed(): void
    {
        $response = $this->requestJson('GET', '/api/client-logs');

        self::assertSame(405, $response->getStatusCode());
    }
}
