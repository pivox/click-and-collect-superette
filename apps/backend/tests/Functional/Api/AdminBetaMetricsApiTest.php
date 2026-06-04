<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Order;
use App\Entity\OrderStatusLog;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use Symfony\Component\HttpFoundation\Response;

final class AdminBetaMetricsApiTest extends FunctionalApiTestCase
{
    public function testAdminReadsAggregatedBetaMetricsByPeriod(): void
    {
        $admin = $this->createUser('admin-beta-metrics@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-beta-metrics@example.test', ['ROLE_CUSTOMER']);
        $shopA = $this->createShop();
        $shopB = $this->createShop();
        $this->createShop(active: true);

        $orderA = $this->createOrder($customer, $shopA);
        $this->createLog($orderA, OrderStatus::Submitted, '2026-06-02T09:00:00+01:00');
        $this->createLog($orderA, OrderStatus::Accepted, '2026-06-02T09:10:00+01:00');
        $this->createLog($orderA, OrderStatus::Completed, '2026-06-02T10:30:00+01:00');

        $orderB = $this->createOrder($customer, $shopA);
        $this->createLog($orderB, OrderStatus::Submitted, '2026-06-03T09:00:00+01:00');
        $this->createLog($orderB, OrderStatus::Rejected, '2026-06-03T09:05:00+01:00');

        $orderC = $this->createOrder($customer, $shopB);
        $this->createLog($orderC, OrderStatus::Submitted, '2026-06-04T09:00:00+01:00');
        $this->createLog($orderC, OrderStatus::PartiallyAccepted, '2026-06-04T09:20:00+01:00');
        $this->createLog($orderC, OrderStatus::Cancelled, '2026-06-04T12:00:00+01:00');

        $outside = $this->createOrder($customer, $shopB);
        $this->createLog($outside, OrderStatus::Submitted, '2026-05-30T09:00:00+01:00');
        $this->createLog($outside, OrderStatus::Completed, '2026-05-30T10:00:00+01:00');

        $response = $this->requestJson('GET', '/api/admin/beta-metrics?date_from=2026-06-01&date_to=2026-06-30', user: $admin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('2026-06-01', $payload['date_from']);
        self::assertSame('2026-06-30', $payload['date_to']);
        self::assertNull($payload['store_id']);
        self::assertSame(3, $payload['submitted']);
        self::assertSame(1, $payload['accepted']);
        self::assertSame(1, $payload['partially_accepted']);
        self::assertSame(1, $payload['rejected']);
        self::assertSame(1, $payload['cancelled']);
        self::assertSame(1, $payload['completed']);
        self::assertSame(0.667, $payload['acceptance_rate']);
        self::assertSame(0.333, $payload['cancellation_rate']);
        self::assertSame(3, $payload['active_stores']);
        self::assertSame(1, $payload['activated_stores']);
        self::assertSame(0.333, $payload['store_activation_rate']);
        self::assertCount(2, $payload['stores']);
        self::assertSame($shopB->getId()->toRfc4122(), $payload['stores'][0]['store_id']);
        self::assertSame('2026-06-04T12:00:00+01:00', $payload['stores'][0]['last_activity_at']);
        self::assertSame($shopA->getId()->toRfc4122(), $payload['stores'][1]['store_id']);
    }

    public function testAdminCanFilterBetaMetricsByStore(): void
    {
        $admin = $this->createUser('admin-beta-store@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-beta-store@example.test', ['ROLE_CUSTOMER']);
        $shopA = $this->createShop();
        $shopB = $this->createShop();

        $orderA = $this->createOrder($customer, $shopA);
        $this->createLog($orderA, OrderStatus::Submitted, '2026-06-02T09:00:00+01:00');
        $this->createLog($orderA, OrderStatus::Completed, '2026-06-02T10:00:00+01:00');

        $orderB = $this->createOrder($customer, $shopB);
        $this->createLog($orderB, OrderStatus::Submitted, '2026-06-02T11:00:00+01:00');
        $this->createLog($orderB, OrderStatus::Rejected, '2026-06-02T12:00:00+01:00');

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/admin/beta-metrics?date_from=2026-06-01&date_to=2026-06-30&store_id=%s', $shopA->getId()),
            user: $admin,
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shopA->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame(1, $payload['submitted']);
        self::assertSame(1, $payload['completed']);
        self::assertSame(1, $payload['active_stores']);
        self::assertSame(1, $payload['activated_stores']);
        self::assertSame(1.0, $payload['store_activation_rate']);
        self::assertCount(1, $payload['stores']);
        self::assertSame($shopA->getId()->toRfc4122(), $payload['stores'][0]['store_id']);
    }

    public function testAdminBetaMetricsRejectsInvertedPeriod(): void
    {
        $admin = $this->createUser('admin-beta-inverted@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/beta-metrics?date_from=2026-06-30&date_to=2026-06-01', user: $admin);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testAnonymousCannotReadBetaMetrics(): void
    {
        $response = $this->requestJson('GET', '/api/admin/beta-metrics?date_from=2026-06-01&date_to=2026-06-30');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testMerchantCannotReadBetaMetrics(): void
    {
        $merchant = $this->createUser('merchant-beta-metrics@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('GET', '/api/admin/beta-metrics?date_from=2026-06-01&date_to=2026-06-30', user: $merchant);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    private function createOrder(User $customer, Shop $shop): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createLog(Order $order, OrderStatus $status, string $createdAt): OrderStatusLog
    {
        $log = new OrderStatusLog($order, $status);
        $this->setPrivateProperty($log, 'createdAt', new \DateTimeImmutable($createdAt));

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }
}
