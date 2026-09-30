<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\RateLimitSubscriber;
use App\Service\RateLimit\FixedWindowCacheRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RateLimitSubscriberTest extends TestCase
{
    /**
     * @var array<string, array{method: string, path?: string, path_prefix?: string, by: string, limit: int, window: int}>
     */
    private const POLICIES = [
        'login_ip_email' => ['method' => 'POST', 'path' => '/api/auth/login', 'by' => 'ip_email', 'limit' => 2, 'window' => 60],
        'login_ip' => ['method' => 'POST', 'path' => '/api/auth/login', 'by' => 'ip', 'limit' => 5, 'window' => 60],
        'store_by_qr_ip' => ['method' => 'GET', 'path_prefix' => '/api/stores/by-qr/', 'by' => 'ip', 'limit' => 3, 'window' => 60],
    ];

    private function createSubscriber(bool $enabled = true): RateLimitSubscriber
    {
        return new RateLimitSubscriber(
            new FixedWindowCacheRateLimiter(new ArrayAdapter(), new MockClock('2026-09-30 10:00:15')),
            $enabled,
            self::POLICIES,
        );
    }

    private function dispatch(RateLimitSubscriber $subscriber, Request $request, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $requestType);
        $subscriber->onRequest($event);

        return $event;
    }

    private function createLoginRequest(string $email, string $ip = '127.0.0.1'): Request
    {
        return Request::create(
            '/api/auth/login',
            'POST',
            server: ['REMOTE_ADDR' => $ip, 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        );
    }

    public function testUnderTheThresholdNoResponseIsSet(): void
    {
        $subscriber = $this->createSubscriber();

        $event = $this->dispatch($subscriber, $this->createLoginRequest('a@example.tn'));
        self::assertFalse($event->hasResponse());

        $event = $this->dispatch($subscriber, $this->createLoginRequest('a@example.tn'));
        self::assertFalse($event->hasResponse());
    }

    public function testAboveTheThresholdReturns429WithRetryAfterAndStableCode(): void
    {
        $subscriber = $this->createSubscriber();

        $this->dispatch($subscriber, $this->createLoginRequest('a@example.tn'));
        $this->dispatch($subscriber, $this->createLoginRequest('a@example.tn'));
        $event = $this->dispatch($subscriber, $this->createLoginRequest('a@example.tn'));

        self::assertTrue($event->hasResponse());
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(429, $response->getStatusCode());
        // MockClock at 10:00:15, 60s window → 45s until the next window.
        self::assertSame('45', $response->headers->get('Retry-After'));
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame('RATE_LIMITED', $payload['detail']);
        self::assertSame(429, $payload['status']);
    }

    public function testAnotherEmailFromTheSameIpIsNotBlockedByTheEmailLimiter(): void
    {
        $subscriber = $this->createSubscriber();

        $this->dispatch($subscriber, $this->createLoginRequest('a@example.tn'));
        $this->dispatch($subscriber, $this->createLoginRequest('a@example.tn'));

        $event = $this->dispatch($subscriber, $this->createLoginRequest('b@example.tn'));
        self::assertFalse($event->hasResponse());
    }

    public function testAnotherIpIsNotBlocked(): void
    {
        $subscriber = $this->createSubscriber();

        $this->dispatch($subscriber, $this->createLoginRequest('a@example.tn'));
        $this->dispatch($subscriber, $this->createLoginRequest('a@example.tn'));
        $this->dispatch($subscriber, $this->createLoginRequest('a@example.tn'));

        $event = $this->dispatch($subscriber, $this->createLoginRequest('a@example.tn', '10.0.0.9'));
        self::assertFalse($event->hasResponse());
    }

    public function testPerIpLoginLimiterCatchesEmailRotation(): void
    {
        $subscriber = $this->createSubscriber();

        foreach (['a', 'b', 'c', 'd', 'e'] as $prefix) {
            $event = $this->dispatch($subscriber, $this->createLoginRequest($prefix.'@example.tn'));
            self::assertFalse($event->hasResponse());
        }

        $event = $this->dispatch($subscriber, $this->createLoginRequest('f@example.tn'));
        self::assertTrue($event->hasResponse());
        self::assertSame(429, $event->getResponse()?->getStatusCode());
    }

    public function testPathPrefixPolicyMatchesQrTokenRoutes(): void
    {
        $subscriber = $this->createSubscriber();

        for ($i = 0; $i < 3; ++$i) {
            $event = $this->dispatch($subscriber, Request::create('/api/stores/by-qr/token-'.$i, 'GET'));
            self::assertFalse($event->hasResponse());
        }

        $event = $this->dispatch($subscriber, Request::create('/api/stores/by-qr/token-x', 'GET'));
        self::assertTrue($event->hasResponse());
        self::assertSame(429, $event->getResponse()?->getStatusCode());
    }

    public function testUnlistedRoutesAreNeverLimited(): void
    {
        $subscriber = $this->createSubscriber();

        for ($i = 0; $i < 30; ++$i) {
            $event = $this->dispatch($subscriber, Request::create('/api/health', 'GET'));
            self::assertFalse($event->hasResponse());
        }
    }

    public function testMethodMismatchIsNotLimited(): void
    {
        $subscriber = $this->createSubscriber();

        for ($i = 0; $i < 10; ++$i) {
            $event = $this->dispatch($subscriber, Request::create('/api/auth/login', 'GET'));
            self::assertFalse($event->hasResponse());
        }
    }

    public function testDisabledSubscriberDoesNothing(): void
    {
        $subscriber = $this->createSubscriber(enabled: false);

        for ($i = 0; $i < 10; ++$i) {
            $event = $this->dispatch($subscriber, $this->createLoginRequest('a@example.tn'));
            self::assertFalse($event->hasResponse());
        }
    }

    public function testSubRequestsAreIgnored(): void
    {
        $subscriber = $this->createSubscriber();

        for ($i = 0; $i < 10; ++$i) {
            $event = $this->dispatch($subscriber, $this->createLoginRequest('a@example.tn'), HttpKernelInterface::SUB_REQUEST);
            self::assertFalse($event->hasResponse());
        }
    }

    public function testMalformedJsonBodyFallsBackToIpOnlyWithoutCrashing(): void
    {
        $subscriber = $this->createSubscriber();

        $request = Request::create(
            '/api/auth/login',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{not-json',
        );
        $event = $this->dispatch($subscriber, $request);

        self::assertFalse($event->hasResponse());
    }
}
