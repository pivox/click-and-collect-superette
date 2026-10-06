<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional coverage of the rate limiting subscriber (#622).
 *
 * The limiter is disabled by default in the test environment (when@test in
 * services.yaml); this class opts back in through the RATE_LIMIT_ENABLED env
 * var, resolved at runtime when the subscriber is instantiated.
 */
final class RateLimitApiTest extends FunctionalApiTestCase
{
    use ClockSensitiveTrait;

    protected function setUp(): void
    {
        $_ENV['RATE_LIMIT_ENABLED'] = '1';
        $_SERVER['RATE_LIMIT_ENABLED'] = '1';

        parent::setUp();

        // The app pool is a filesystem cache shared across suite runs: start
        // from a clean state so leftover counters cannot make the test flaky.
        $cache = self::getContainer()->get('cache.app');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $cache->clear();
    }

    protected function tearDown(): void
    {
        unset($_ENV['RATE_LIMIT_ENABLED'], $_SERVER['RATE_LIMIT_ENABLED']);

        parent::tearDown();
    }

    public function testPasswordResetRequestIsThrottledPerIpWith429AndRetryAfter(): void
    {
        for ($i = 1; $i <= 3; ++$i) {
            $response = $this->requestJson('POST', '/api/auth/password-reset/request', [
                'email' => \sprintf('rate-limit-%d@example.test', $i),
            ]);
            self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode(), \sprintf('Request %d should pass under the threshold', $i));
        }

        $throttled = $this->requestJson('POST', '/api/auth/password-reset/request', [
            'email' => 'rate-limit-4@example.test',
        ]);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $throttled->getStatusCode());
        self::assertSame('application/problem+json', $throttled->headers->get('Content-Type'));

        $retryAfter = $throttled->headers->get('Retry-After');
        self::assertIsString($retryAfter);
        self::assertGreaterThan(0, (int) $retryAfter);
        self::assertLessThanOrEqual(900, (int) $retryAfter);

        $payload = $this->decodeJson($throttled);
        self::assertSame('RATE_LIMITED', $payload['detail']);
        self::assertSame(429, $payload['status']);

        // Correlation id (#617) must survive the early 429 short-circuit.
        self::assertNotNull($throttled->headers->get('X-Request-Id'));
    }

    public function testPickupCodeIsThrottledPerMerchantAndShopAfterAuthorization(): void
    {
        $clock = self::mockTime('2026-10-03 10:00:00');
        $merchant = $this->createUser('merchant-rate-redeem@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-rate-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $otherShop = $this->createShop($merchant);
        $logger = self::getContainer()->get('logger');
        self::assertInstanceOf(Logger::class, $logger);
        $handler = new TestHandler();
        $logger->pushHandler($handler);
        $url = \sprintf('/api/merchant/stores/%s/orders/redeem-by-code', $shop->getId());
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            self::assertSame(403, $this->requestJson('POST', $url, ['pickupCode' => '1234'], $otherMerchant)->getStatusCode());
            self::assertSame(404, $this->requestJson('POST', $url, ['pickupCode' => '1234'], $merchant)->getStatusCode());
        }
        $response = $this->requestJson('POST', $url, ['pickupCode' => '5678'], $merchant);
        self::assertSame(429, $response->getStatusCode());
        self::assertStringContainsString('RATE_LIMITED', (string) $response->getContent());
        self::assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $records = array_values(array_filter($handler->getRecords(), static fn ($record): bool => str_starts_with($record->message, 'pickup_code_')));
        self::assertCount(6, $records);
        foreach ($records as $record) {
            self::assertSame(['store_id', 'merchant_identifier_hash'], array_keys($record->context));
        }
        self::assertSame('pickup_code_rate_limited', $records[5]->message);
        $clock->sleep(60);
        self::assertSame(404, $this->requestJson('POST', $url, ['pickupCode' => '1234'], $merchant)->getStatusCode());
        self::assertSame(404, $this->requestJson('POST', \sprintf('/api/merchant/stores/%s/orders/redeem-by-code', $otherShop->getId()), ['pickupCode' => '1234'], $merchant)->getStatusCode());
    }

    public function testUnlistedRouteStaysReachableWhileAnotherRouteIsThrottled(): void
    {
        for ($i = 1; $i <= 4; ++$i) {
            $this->requestJson('POST', '/api/auth/password-reset/request', [
                'email' => \sprintf('rate-limit-unlisted-%d@example.test', $i),
            ]);
        }

        $health = $this->requestJson('GET', '/api/health');
        self::assertSame(Response::HTTP_OK, $health->getStatusCode());
    }
}
