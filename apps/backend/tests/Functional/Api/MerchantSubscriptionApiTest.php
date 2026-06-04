<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Subscription;
use App\Enum\SubscriptionLifecycle;

final class MerchantSubscriptionApiTest extends FunctionalApiTestCase
{
    public function testMerchantCanReadTheirOwnSubscription(): void
    {
        $merchant = $this->createUser('merchant-subscription-read@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $subscription->setLifecycle(SubscriptionLifecycle::Active);
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/merchant/subscription', user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($subscription->getId()->toRfc4122(), $payload['id']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['merchant_id']);
        self::assertSame('active', $payload['lifecycle']);
        self::assertSame('trial', $payload['pricing_phase']);
        self::assertSame('0.000', $payload['monthly_price_tnd']);
        self::assertSame('TND', $payload['currency']);
        self::assertSame('2026-06-01T00:00:00+01:00', $payload['started_at']);
        self::assertSame('2026-09-01T00:00:00+01:00', $payload['next_phase_change_at']);
        self::assertArrayNotHasKey('payment_method', $payload);
        self::assertArrayNotHasKey('invoice', $payload);
    }

    public function testMerchantSubscriptionRequiresMerchantRole(): void
    {
        $customer = $this->createUser('merchant-subscription-customer@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('GET', '/api/merchant/subscription', user: $customer);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testMerchantSubscriptionReturns404WhenMissing(): void
    {
        $merchant = $this->createUser('merchant-subscription-missing@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('GET', '/api/merchant/subscription', user: $merchant);

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('MERCHANT_SUBSCRIPTION_NOT_FOUND', (string) $response->getContent());
    }
}
