<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Subscription;
use App\Enum\SubscriptionLifecycle;
use Symfony\Component\Uid\Uuid;

final class AdminSubscriptionApiTest extends FunctionalApiTestCase
{
    public function testAdminCanListMerchantSubscriptions(): void
    {
        $admin = $this->createUser('admin-subscription-list@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-subscription-list@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $subscription->setLifecycle(SubscriptionLifecycle::PaymentDue);
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/admin/subscriptions', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(20, $payload['limit']);
        self::assertCount(1, $payload['items']);
        self::assertSame($subscription->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['items'][0]['merchant_id']);
        self::assertSame('merchant-subscription-list@example.test', $payload['items'][0]['merchant_email']);
        self::assertSame('payment_due', $payload['items'][0]['lifecycle']);
        self::assertSame('trial', $payload['items'][0]['pricing_phase']);
        self::assertSame('0.000', $payload['items'][0]['monthly_price_tnd']);
    }

    public function testAdminCanReadSubscriptionDetail(): void
    {
        $admin = $this->createUser('admin-subscription-detail@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-subscription-detail@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-09-01T00:00:00+01:00'));
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/admin/subscriptions/%s', $subscription->getId()), user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($subscription->getId()->toRfc4122(), $payload['id']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['merchant_id']);
        self::assertSame('merchant-subscription-detail@example.test', $payload['merchant_email']);
        self::assertSame('active', $payload['lifecycle']);
        self::assertSame('trial', $payload['pricing_phase']);
        self::assertSame('2026-12-01T00:00:00+01:00', $payload['next_phase_change_at']);
    }

    public function testMerchantIsForbiddenFromAdminSubscriptionEndpoints(): void
    {
        $merchant = $this->createUser('merchant-admin-subscription-forbidden@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        $listResponse = $this->requestJson('GET', '/api/admin/subscriptions', user: $merchant);
        $detailResponse = $this->requestJson('GET', \sprintf('/api/admin/subscriptions/%s', $subscription->getId()), user: $merchant);

        self::assertSame(403, $listResponse->getStatusCode());
        self::assertSame(403, $detailResponse->getStatusCode());
    }

    public function testAdminSubscriptionDetailReturns404WhenMissing(): void
    {
        $admin = $this->createUser('admin-subscription-missing@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', \sprintf('/api/admin/subscriptions/%s', Uuid::v4()), user: $admin);

        self::assertSame(404, $response->getStatusCode());
    }
}
