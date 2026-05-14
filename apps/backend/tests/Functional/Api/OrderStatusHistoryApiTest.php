<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Order;
use App\Entity\OrderStatusLog;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;

final class OrderStatusHistoryApiTest extends FunctionalApiTestCase
{
    public function testCustomerCanReadOwnOrderStatusHistoryChronologically(): void
    {
        $customer = $this->createUser('customer-history-owner@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createOrder($customer, $shop);
        $this->createLog($order, OrderStatus::Accepted, null, new \DateTimeImmutable('2026-05-14T10:45:00+01:00'));
        $this->createLog($order, OrderStatus::Submitted, null, new \DateTimeImmutable('2026-05-14T10:32:00+01:00'));

        self::assertCount(2, $this->entityManager->getRepository(OrderStatusLog::class)->findAll());

        $response = $this->requestJson('GET', \sprintf('/api/me/orders/%s/status-history', $order->getId()), null, $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($order->getId()->toRfc4122(), $payload['order_id']);
        self::assertCount(2, $payload['transitions']);
        self::assertSame('submitted', $payload['transitions'][0]['status']);
        self::assertNull($payload['transitions'][0]['note']);
        self::assertSame('accepted', $payload['transitions'][1]['status']);
        self::assertArrayNotHasKey('store_id', $payload['transitions'][0]);
    }

    public function testCustomerCannotReadAnotherCustomerOrderStatusHistory(): void
    {
        $owner = $this->createUser('customer-history-owner-denied@example.test', ['ROLE_CUSTOMER']);
        $other = $this->createUser('customer-history-other@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createOrder($owner, $shop);
        $this->createLog($order, OrderStatus::Submitted);

        $response = $this->requestJson('GET', \sprintf('/api/me/orders/%s/status-history', $order->getId()), null, $other);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testCustomerStatusHistoryUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson('GET', '/api/me/orders/00000000-0000-0000-0000-000000000099/status-history');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testMerchantOwnerCanReadOrderStatusHistoryChronologically(): void
    {
        $merchant = $this->createUser('merchant-history-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-history-merchant@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createOrder($customer, $shop);
        $this->createLog($order, OrderStatus::Ready, null, new \DateTimeImmutable('2026-05-14T11:28:00+01:00'));
        $this->createLog($order, OrderStatus::Rejected, 'Rupture de stock', new \DateTimeImmutable('2026-05-14T10:45:00+01:00'));

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s/status-history', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($order->getId()->toRfc4122(), $payload['order_id']);
        self::assertCount(2, $payload['transitions']);
        self::assertSame('rejected', $payload['transitions'][0]['status']);
        self::assertSame('Rupture de stock', $payload['transitions'][0]['note']);
        self::assertSame('ready', $payload['transitions'][1]['status']);
        self::assertArrayNotHasKey('customer_id', $payload['transitions'][0]);
    }

    public function testMerchantFromAnotherStoreIsForbidden(): void
    {
        $merchantA = $this->createUser('merchant-history-a@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-history-b@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchantB);
        $customer = $this->createUser('customer-history-forbidden@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createOrder($customer, $shop);
        $this->createLog($order, OrderStatus::Submitted);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s/status-history', $shop->getId(), $order->getId()),
            null,
            $merchantA,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testCustomerCannotCallMerchantStatusHistory(): void
    {
        $customer = $this->createUser('customer-history-merchant-endpoint@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/00000000-0000-0000-0000-000000000099/status-history', $shop->getId()),
            null,
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testMerchantStatusHistoryUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/00000000-0000-0000-0000-000000000099/status-history', $shop->getId()),
        );

        self::assertSame(401, $response->getStatusCode());
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

    private function createLog(
        Order $order,
        OrderStatus $status,
        ?string $note = null,
        ?\DateTimeImmutable $createdAt = null,
    ): OrderStatusLog {
        $log = new OrderStatusLog($order, $status, $note);
        if (null !== $createdAt) {
            $reflectionProperty = new \ReflectionProperty($log, 'createdAt');
            $reflectionProperty->setValue($log, $createdAt);
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }
}
