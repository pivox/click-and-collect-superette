<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Order;
use App\Entity\PickupSlot;
use App\Entity\Shop;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;

final class MerchantOrderApiTest extends FunctionalApiTestCase
{
    // ---------------------------------------------------------------------------
    // GET /api/merchant/stores/{storeId}/orders
    // ---------------------------------------------------------------------------

    public function testListOrdersHappyPath(): void
    {
        $merchant = $this->createUser('merchant-list@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-list@example.test', ['ROLE_CUSTOMER']);

        $this->createSubmittedOrder($customer, $shop);
        $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders', $shop->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame(2, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertCount(2, $payload['items']);
        self::assertSame('submitted', $payload['items'][0]['status']);
    }

    public function testListOrdersFilterByStatus(): void
    {
        $merchant = $this->createUser('merchant-filter@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-filter@example.test', ['ROLE_CUSTOMER']);

        $this->createSubmittedOrder($customer, $shop);
        $this->createAcceptedOrder($customer, $shop);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders?status=accepted', $shop->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertCount(1, $payload['items']);
        self::assertSame('accepted', $payload['items'][0]['status']);
    }

    public function testListOrdersWrongMerchantReturns403(): void
    {
        $merchantA = $this->createUser('merchant-a-list@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-b-list@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchantB);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders', $shop->getId()),
            null,
            $merchantA,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testListOrdersUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders', $shop->getId()),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testListOrdersCustomerRoleReturns403(): void
    {
        $customer = $this->createUser('customer-role-list@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders', $shop->getId()),
            null,
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // ---------------------------------------------------------------------------
    // POST /api/merchant/stores/{storeId}/orders/{orderId}/accept
    // ---------------------------------------------------------------------------

    public function testAcceptOrderHappyPath(): void
    {
        $merchant = $this->createUser('merchant-accept@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-accept@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/accept', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('accepted', $payload['status']);
        self::assertSame($order->getId()->toRfc4122(), $payload['id']);

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updated);
        self::assertSame('accepted', $updated->getStatus()->value);
    }

    public function testAcceptOrderInvalidTransitionReturns409(): void
    {
        $merchant = $this->createUser('merchant-accept-409@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-accept-409@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createAcceptedOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/accept', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_SUBMITTED', (string) $response->getContent());
    }

    public function testAcceptOrderFromAnotherShopReturns404(): void
    {
        $merchant = $this->createUser('merchant-accept-404@example.test', ['ROLE_MERCHANT']);
        $shop1 = $this->createShop($merchant);
        $shop2 = $this->createShop();
        $customer = $this->createUser('customer-accept-404@example.test', ['ROLE_CUSTOMER']);
        $orderInShop2 = $this->createSubmittedOrder($customer, $shop2);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/accept', $shop1->getId(), $orderInShop2->getId()),
            null,
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_FOUND', (string) $response->getContent());
    }

    public function testAcceptOrderUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/accept', $shop->getId(), Uuid::v4()->toRfc4122()),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testAcceptOrderCustomerRoleReturns403(): void
    {
        $customer = $this->createUser('customer-role-accept@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/accept', $shop->getId(), Uuid::v4()->toRfc4122()),
            null,
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // ---------------------------------------------------------------------------
    // POST /api/merchant/stores/{storeId}/orders/{orderId}/reject
    // ---------------------------------------------------------------------------

    public function testRejectOrderHappyPath(): void
    {
        $merchant = $this->createUser('merchant-reject@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop);
        $customer = $this->createUser('customer-reject@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrderWithSlot($customer, $shop, $slot);

        $slot->book();
        $this->entityManager->flush();
        $bookedBefore = $slot->getBookedCount();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/reject', $shop->getId(), $order->getId()),
            ['reason' => 'Rupture de stock'],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('rejected', $payload['status']);

        $this->entityManager->clear();
        $updatedSlot = $this->entityManager->getRepository(PickupSlot::class)->find($slot->getId());
        self::assertNotNull($updatedSlot);
        self::assertSame($bookedBefore - 1, $updatedSlot->getBookedCount());
    }

    public function testRejectOrderInvalidTransitionReturns409(): void
    {
        $merchant = $this->createUser('merchant-reject-409@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-reject-409@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createAcceptedOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/reject', $shop->getId(), $order->getId()),
            ['reason' => 'Trop tard'],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_SUBMITTED', (string) $response->getContent());
    }

    public function testRejectOrderFromAnotherShopReturns404(): void
    {
        $merchant = $this->createUser('merchant-reject-404@example.test', ['ROLE_MERCHANT']);
        $shop1 = $this->createShop($merchant);
        $shop2 = $this->createShop();
        $customer = $this->createUser('customer-reject-404@example.test', ['ROLE_CUSTOMER']);
        $orderInShop2 = $this->createSubmittedOrder($customer, $shop2);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/reject', $shop1->getId(), $orderInShop2->getId()),
            ['reason' => 'Test'],
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRejectOrderUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/reject', $shop->getId(), Uuid::v4()->toRfc4122()),
            ['reason' => 'Test'],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testRejectOrderCustomerRoleReturns403(): void
    {
        $customer = $this->createUser('customer-role-reject@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/reject', $shop->getId(), Uuid::v4()->toRfc4122()),
            ['reason' => 'Test'],
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // ---------------------------------------------------------------------------
    // POST /api/merchant/stores/{storeId}/orders/{orderId}/start-preparation
    // ---------------------------------------------------------------------------

    public function testStartPreparationHappyPath(): void
    {
        $merchant = $this->createUser('merchant-prep@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-prep@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createAcceptedOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/start-preparation', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('preparing', $payload['status']);
    }

    public function testStartPreparationInvalidTransitionReturns409(): void
    {
        $merchant = $this->createUser('merchant-prep-409@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-prep-409@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/start-preparation', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_ACCEPTED', (string) $response->getContent());
    }

    public function testStartPreparationFromAnotherShopReturns404(): void
    {
        $merchant = $this->createUser('merchant-prep-404@example.test', ['ROLE_MERCHANT']);
        $shop1 = $this->createShop($merchant);
        $shop2 = $this->createShop();
        $customer = $this->createUser('customer-prep-404@example.test', ['ROLE_CUSTOMER']);
        $orderInShop2 = $this->createAcceptedOrder($customer, $shop2);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/start-preparation', $shop1->getId(), $orderInShop2->getId()),
            null,
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testStartPreparationUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/start-preparation', $shop->getId(), Uuid::v4()->toRfc4122()),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testStartPreparationCustomerRoleReturns403(): void
    {
        $customer = $this->createUser('customer-role-prep@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/start-preparation', $shop->getId(), Uuid::v4()->toRfc4122()),
            null,
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // ---------------------------------------------------------------------------
    // POST /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready
    // ---------------------------------------------------------------------------

    public function testMarkReadyHappyPath(): void
    {
        $merchant = $this->createUser('merchant-ready@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-ready@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createPreparingOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/mark-ready', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('ready', $payload['status']);
    }

    public function testMarkReadyInvalidTransitionReturns409(): void
    {
        $merchant = $this->createUser('merchant-ready-409@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-ready-409@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/mark-ready', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_PREPARING', (string) $response->getContent());
    }

    public function testMarkReadyFromAnotherShopReturns404(): void
    {
        $merchant = $this->createUser('merchant-ready-404@example.test', ['ROLE_MERCHANT']);
        $shop1 = $this->createShop($merchant);
        $shop2 = $this->createShop();
        $customer = $this->createUser('customer-ready-404@example.test', ['ROLE_CUSTOMER']);
        $orderInShop2 = $this->createPreparingOrder($customer, $shop2);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/mark-ready', $shop1->getId(), $orderInShop2->getId()),
            null,
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testMarkReadyUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/mark-ready', $shop->getId(), Uuid::v4()->toRfc4122()),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testMarkReadyCustomerRoleReturns403(): void
    {
        $customer = $this->createUser('customer-role-ready@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/mark-ready', $shop->getId(), Uuid::v4()->toRfc4122()),
            null,
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function createSubmittedOrder(User $customer, Shop $shop): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->submit();
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createSubmittedOrderWithSlot(User $customer, Shop $shop, PickupSlot $slot): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setPickupSlot($slot);
        $order->submit();
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createAcceptedOrder(User $customer, Shop $shop): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->submit();
        $order->accept();
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createPreparingOrder(User $customer, Shop $shop): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createPickupSlot(Shop $shop): PickupSlot
    {
        $now = new \DateTimeImmutable();
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(5);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }
}
