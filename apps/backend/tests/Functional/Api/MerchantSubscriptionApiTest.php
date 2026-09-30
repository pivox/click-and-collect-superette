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
        // Relative start date: a hardcoded one rots once the 3-month trial
        // window passes (the output exposes the effective phase).
        $startedAt = new \DateTimeImmutable('first day of last month midnight', new \DateTimeZone('+01:00'));
        $subscription = Subscription::startTrial($merchant, $startedAt);
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
        self::assertSame($startedAt->format(\DateTimeInterface::ATOM), $payload['started_at']);
        self::assertSame($startedAt->modify('+3 months')->format(\DateTimeInterface::ATOM), $payload['next_phase_change_at']);
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
