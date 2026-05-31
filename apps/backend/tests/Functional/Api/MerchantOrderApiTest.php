<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Kadhia;
use App\Entity\KadhiaLine;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\OrderStatusLog;
use App\Entity\PickupSlot;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\KadhiaStatus;
use App\Enum\OrderStatus;
use App\Enum\ProductReferenceStatus;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $slot = $this->createPickupSlot($shop);
        $orderWithSlot = $this->createSubmittedOrderWithSlot($customer, $shop, $slot);
        $orderWithSlot->assignOrderNumber(42);
        $this->entityManager->flush();
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
        self::assertArrayHasKey('line_count', $payload['items'][0]);
        self::assertArrayNotHasKey('lines', $payload['items'][0]);
        self::assertArrayNotHasKey('pickup_slot_id', $payload['items'][0]);
        $itemWithSlot = array_values(array_filter(
            $payload['items'],
            static fn (array $item): bool => isset($item['pickup_slot']['id']),
        ));
        self::assertCount(1, $itemWithSlot);
        self::assertSame($slot->getId()->toRfc4122(), $itemWithSlot[0]['pickup_slot']['id']);
        self::assertSame(42, $itemWithSlot[0]['order_number']);
        self::assertSame('#0042', $itemWithSlot[0]['order_number_display']);
    }

    public function testListOrdersDoesNotExposeCustomerContactOrSensitiveUserData(): void
    {
        $merchant = $this->createUser('merchant-list-private@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-list-private@example.test', ['ROLE_CUSTOMER']);
        $customer->setName('Client Privé')->setPhone('+21622123456');
        $this->entityManager->flush();

        $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders', $shop->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        $item = $payload['items'][0];

        self::assertArrayNotHasKey('customer_name', $item);
        self::assertArrayNotHasKey('customer_phone', $item);
        self::assertArrayNotHasKey('customer_email', $item);
        self::assertArrayNotHasKey('password', $item);
        self::assertArrayNotHasKey('roles', $item);
        self::assertArrayNotHasKey('token', $item);
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
    // GET /api/merchant/stores/{storeId}/orders/{orderId}
    // ---------------------------------------------------------------------------

    public function testGetOrderDetailHappyPathIncludesLinesAndCustomerContact(): void
    {
        $merchant = $this->createUser('merchant-detail@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop);
        $customer = $this->createUser('customer-detail@example.test', ['ROLE_CUSTOMER']);
        $customer->setName('Amira Ben Salah')->setPhone('+21622111222');
        $this->entityManager->flush();
        $product = $this->createMerchantProduct($shop, '2.500', 'Lait Vitalait 1L');
        $order = $this->createSubmittedOrderWithSlot($customer, $shop, $slot);
        $this->addOrderLine($order, $product, quantity: 2, unitPriceTnd: '2.500');
        $order->assignOrderNumber(43);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame($order->getId()->toRfc4122(), $payload['id']);
        self::assertSame(43, $payload['order_number']);
        self::assertSame('#0043', $payload['order_number_display']);
        self::assertSame('submitted', $payload['status']);
        self::assertSame('5.000', $payload['total_tnd']);
        self::assertSame($slot->getId()->toRfc4122(), $payload['pickup_slot']['id']);
        self::assertSame('Amira Ben Salah', $payload['customer_name']);
        self::assertSame('+21622111222', $payload['customer_phone']);
        self::assertArrayNotHasKey('customer_email', $payload);
        self::assertCount(1, $payload['lines']);
        self::assertSame($product->getId()->toRfc4122(), $payload['lines'][0]['merchant_product_id']);
        self::assertSame('Lait Vitalait 1L', $payload['lines'][0]['product_name']);
        self::assertSame(2, $payload['lines'][0]['quantity']);
        self::assertSame('2.500', $payload['lines'][0]['unit_price_tnd']);
        self::assertSame('5.000', $payload['lines'][0]['line_total_tnd']);
        self::assertFalse($payload['lines'][0]['prepared']);
    }

    public function testGetOrderDetailAllowsNullableCustomerPhone(): void
    {
        $merchant = $this->createUser('merchant-detail-phone-null@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-detail-phone-null@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertArrayHasKey('customer_phone', $payload);
        self::assertNull($payload['customer_phone']);
    }

    public function testGetOrderDetailWrongMerchantReturns403(): void
    {
        $merchantA = $this->createUser('merchant-a-detail@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-b-detail@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchantB);
        $customer = $this->createUser('customer-detail-forbidden@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s', $shop->getId(), $order->getId()),
            null,
            $merchantA,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testGetOrderDetailFromAnotherShopReturns404(): void
    {
        $merchant = $this->createUser('merchant-detail-404@example.test', ['ROLE_MERCHANT']);
        $shop1 = $this->createShop($merchant);
        $shop2 = $this->createShop();
        $customer = $this->createUser('customer-detail-404@example.test', ['ROLE_CUSTOMER']);
        $orderInShop2 = $this->createSubmittedOrder($customer, $shop2);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s', $shop1->getId(), $orderInShop2->getId()),
            null,
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetOrderDetailUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s', $shop->getId(), Uuid::v4()->toRfc4122()),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testGetOrderDetailCustomerRoleReturns403(): void
    {
        $customer = $this->createUser('customer-role-detail@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s', $shop->getId(), Uuid::v4()->toRfc4122()),
            null,
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testGetOrderDetailDoesNotExposeSensitiveUserData(): void
    {
        $merchant = $this->createUser('merchant-detail-private@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-detail-private@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $content = (string) $response->getContent();
        $payload = $this->decodeJson($response);

        self::assertArrayNotHasKey('password', $payload);
        self::assertArrayNotHasKey('roles', $payload);
        self::assertArrayNotHasKey('token', $payload);
        self::assertStringNotContainsString('test-password', $content);
        self::assertStringNotContainsString('ROLE_CUSTOMER', $content);
    }

    public function testGetOrderDetailDoesNotExposeCustomerContactOnRejectedOrder(): void
    {
        $merchant = $this->createUser('merchant-detail-rejected-private@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-detail-rejected-private@example.test', ['ROLE_CUSTOMER']);
        $customer->setName('Client Refusé')->setPhone('+21622999000');
        $order = $this->createRejectedOrder($customer, $shop);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertNull($payload['customer_name']);
        self::assertNull($payload['customer_phone']);
        self::assertArrayNotHasKey('customer_email', $payload);
    }

    public function testGetOrderDetailDoesNotExposeCustomerContactOnCompletedOrder(): void
    {
        $merchant = $this->createUser('merchant-detail-completed-private@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-detail-completed-private@example.test', ['ROLE_CUSTOMER']);
        $customer->setName('Client Terminé')->setPhone('+21622999111');
        $order = $this->createCompletedOrder($customer, $shop);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertNull($payload['customer_name']);
        self::assertNull($payload['customer_phone']);
        self::assertArrayNotHasKey('customer_email', $payload);
    }

    public function testGetOrderDetailDoesNotExposeCustomerContactOnCancelledOrder(): void
    {
        $merchant = $this->createUser('merchant-detail-cancelled-private@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-detail-cancelled-private@example.test', ['ROLE_CUSTOMER']);
        $customer->setName('Client Annulé')->setPhone('+21622999222');
        $order = $this->createCancelledOrder($customer, $shop);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertNull($payload['customer_name']);
        self::assertNull($payload['customer_phone']);
        self::assertArrayNotHasKey('customer_email', $payload);
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

        $logs = $this->findStatusLogs($order);
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::Accepted, $logs[0]->getStatus());
        self::assertNull($logs[0]->getNote());

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updated);
        self::assertSame('accepted', $updated->getStatus()->value);
    }

    public function testAcceptOrderLogIsVisibleInMerchantStatusHistory(): void
    {
        $merchant = $this->createUser('merchant-accept-history@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-accept-history@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $acceptResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/accept', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $acceptResponse->getStatusCode());

        $historyResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s/status-history', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $historyResponse->getStatusCode());

        $payload = $this->decodeJson($historyResponse);
        self::assertCount(1, $payload['transitions']);
        self::assertSame('accepted', $payload['transitions'][0]['status']);
        self::assertNull($payload['transitions'][0]['note']);
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

    public function testAcceptOrderWrongMerchantReturns403(): void
    {
        $merchantA = $this->createUser('merchant-a-accept@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-b-accept@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchantB);
        $customer = $this->createUser('customer-accept-forbidden@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/accept', $shop->getId(), $order->getId()),
            null,
            $merchantA,
        );

        self::assertSame(403, $response->getStatusCode());
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
        self::assertSame('Rupture de stock', $payload['rejection_reason']);

        $logs = $this->findStatusLogs($order);
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::Rejected, $logs[0]->getStatus());
        self::assertSame('Rupture de stock', $logs[0]->getNote());

        $this->entityManager->clear();
        $updatedSlot = $this->entityManager->getRepository(PickupSlot::class)->find($slot->getId());
        self::assertNotNull($updatedSlot);
        self::assertSame($bookedBefore - 1, $updatedSlot->getBookedCount());

        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame('Rupture de stock', $updatedOrder->getRejectionReason());
    }

    public function testRejectOrderWithoutReasonStoresNullReasonAndLogNote(): void
    {
        $merchant = $this->createUser('merchant-reject-null-reason@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-reject-null-reason@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/reject', $shop->getId(), $order->getId()),
            [],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('rejected', $payload['status']);
        self::assertNull($payload['rejection_reason']);

        $logs = $this->findStatusLogs($order);
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::Rejected, $logs[0]->getStatus());
        self::assertNull($logs[0]->getNote());
    }

    public function testRejectOrderWithWhitespaceOnlyReasonStoresNullReasonAndLogNote(): void
    {
        $merchant = $this->createUser('merchant-reject-blank-reason@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-reject-blank-reason@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/reject', $shop->getId(), $order->getId()),
            ['reason' => '   '],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('rejected', $payload['status']);
        self::assertNull($payload['rejection_reason']);

        $logs = $this->findStatusLogs($order);
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::Rejected, $logs[0]->getStatus());
        self::assertNull($logs[0]->getNote());
    }

    public function testRejectOrderLogNoteIsVisibleInMerchantStatusHistory(): void
    {
        $merchant = $this->createUser('merchant-reject-history@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-reject-history@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $rejectResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/reject', $shop->getId(), $order->getId()),
            ['reason' => 'Créneau non disponible'],
            $merchant,
        );

        self::assertSame(200, $rejectResponse->getStatusCode());

        $historyResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s/status-history', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $historyResponse->getStatusCode());

        $payload = $this->decodeJson($historyResponse);
        self::assertCount(1, $payload['transitions']);
        self::assertSame('rejected', $payload['transitions'][0]['status']);
        self::assertSame('Créneau non disponible', $payload['transitions'][0]['note']);
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

    public function testRejectOrderWrongMerchantReturns403(): void
    {
        $merchantA = $this->createUser('merchant-a-reject@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-b-reject@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchantB);
        $customer = $this->createUser('customer-reject-forbidden@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/reject', $shop->getId(), $order->getId()),
            ['reason' => 'Test'],
            $merchantA,
        );

        self::assertSame(403, $response->getStatusCode());
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
    // POST /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept
    // ---------------------------------------------------------------------------

    public function testPartiallyAcceptOrderHappyPathUpdatesKadhiaAndKeepsSlotReserved(): void
    {
        $merchant = $this->createUser('merchant-partial@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop, '+5 hours', '+6 hours');
        $customer = $this->createUser('customer-partial@example.test', ['ROLE_CUSTOMER']);
        $productA = $this->createMerchantProduct($shop, '2.000', 'Lait Vitalait 1L');
        $productB = $this->createMerchantProduct($shop, '1.500', 'Yaourt nature');
        $productC = $this->createMerchantProduct($shop, '3.000', 'Café moulu');
        $kadhia = $this->createSubmittedKadhiaWithLines($customer, $shop, [$productA, $productB, $productC]);
        $order = $this->createSubmittedOrderFromKadhia($customer, $shop, $kadhia, $slot);
        $slot->book();
        $this->entityManager->flush();
        $bookedBefore = $slot->getBookedCount();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/partially-accept', $shop->getId(), $order->getId()),
            [
                'rejected_merchant_product_ids' => [$productB->getId()->toRfc4122()],
                'notes' => 'Rupture de stock Vitalait 1L.',
            ],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('partially_accepted', $payload['status']);
        self::assertSame('Rupture de stock Vitalait 1L.', $payload['rejection_reason']);

        $logs = $this->findStatusLogs($order);
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::PartiallyAccepted, $logs[0]->getStatus());
        self::assertSame('Rupture de stock Vitalait 1L.', $logs[0]->getNote());

        $historyResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s/status-history', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $historyResponse->getStatusCode());
        $historyPayload = $this->decodeJson($historyResponse);
        self::assertCount(1, $historyPayload['transitions']);
        self::assertSame('partially_accepted', $historyPayload['transitions'][0]['status']);
        self::assertSame('Rupture de stock Vitalait 1L.', $historyPayload['transitions'][0]['note']);

        $this->entityManager->clear();

        $updatedKadhia = $this->entityManager->getRepository(Kadhia::class)->find($kadhia->getId());
        self::assertNotNull($updatedKadhia);
        self::assertSame(KadhiaStatus::Draft, $updatedKadhia->getStatus());
        self::assertEqualsCanonicalizing(
            [$productA->getId()->toRfc4122(), $productC->getId()->toRfc4122()],
            array_map(
                static fn (KadhiaLine $line): string => $line->getMerchantProduct()->getId()->toRfc4122(),
                $updatedKadhia->getLines()->toArray(),
            ),
        );

        $updatedSlot = $this->entityManager->getRepository(PickupSlot::class)->find($slot->getId());
        self::assertNotNull($updatedSlot);
        self::assertSame($bookedBefore, $updatedSlot->getBookedCount());
    }

    public function testPartiallyAcceptOrderWithoutNotesStoresNullReasonAndLogNote(): void
    {
        $merchant = $this->createUser('merchant-partial-null-note@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop);
        $customer = $this->createUser('customer-partial-null-note@example.test', ['ROLE_CUSTOMER']);
        $productA = $this->createMerchantProduct($shop, '2.000');
        $productB = $this->createMerchantProduct($shop, '1.500');
        $kadhia = $this->createSubmittedKadhiaWithLines($customer, $shop, [$productA, $productB]);
        $order = $this->createSubmittedOrderFromKadhia($customer, $shop, $kadhia, $slot);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/partially-accept', $shop->getId(), $order->getId()),
            ['rejected_merchant_product_ids' => [$productB->getId()->toRfc4122()], 'notes' => '   '],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('partially_accepted', $payload['status']);
        self::assertNull($payload['rejection_reason']);

        $logs = $this->findStatusLogs($order);
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::PartiallyAccepted, $logs[0]->getStatus());
        self::assertNull($logs[0]->getNote());
    }

    public function testPartiallyAcceptedKadhiaCanBeResubmittedOnSameOrder(): void
    {
        $merchant = $this->createUser('merchant-partial-resubmit@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop, '+5 hours', '+6 hours');
        $customer = $this->createUser('customer-partial-resubmit@example.test', ['ROLE_CUSTOMER']);
        $productA = $this->createMerchantProduct($shop, '2.000');
        $productB = $this->createMerchantProduct($shop, '1.500');
        $kadhia = $this->createSubmittedKadhiaWithLines($customer, $shop, [$productA, $productB]);
        $order = $this->createSubmittedOrderFromKadhia($customer, $shop, $kadhia, $slot);
        $slot->book();
        $this->entityManager->flush();

        $partialResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/partially-accept', $shop->getId(), $order->getId()),
            [
                'rejected_merchant_product_ids' => [$productB->getId()->toRfc4122()],
                'notes' => 'Produit indisponible',
            ],
            $merchant,
        );

        self::assertSame(200, $partialResponse->getStatusCode());

        $submitResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/submit', $kadhia->getId()),
            ['pickup_slot_id' => $slot->getId()->toRfc4122()],
            $customer,
        );

        self::assertSame(201, $submitResponse->getStatusCode());

        $payload = $this->decodeJson($submitResponse);
        self::assertSame($order->getId()->toRfc4122(), $payload['id']);
        self::assertSame('submitted', $payload['status']);
        self::assertCount(1, $payload['lines']);

        $this->entityManager->clear();

        $orders = $this->entityManager->getRepository(Order::class)->findAll();
        self::assertCount(1, $orders);
        self::assertNull($orders[0]->getRejectionReason());
        self::assertCount(1, $orders[0]->getLines());
        self::assertSame(
            $productA->getId()->toRfc4122(),
            $orders[0]->getLines()->first()->getMerchantProduct()->getId()->toRfc4122(),
        );

        $updatedSlot = $this->entityManager->getRepository(PickupSlot::class)->find($slot->getId());
        self::assertNotNull($updatedSlot);
        self::assertSame(1, $updatedSlot->getBookedCount());
    }

    public function testPartiallyAcceptOrderRejectsEmptyRejectedLines(): void
    {
        $merchant = $this->createUser('merchant-partial-empty@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop);
        $customer = $this->createUser('customer-partial-empty@example.test', ['ROLE_CUSTOMER']);
        $productA = $this->createMerchantProduct($shop, '2.000');
        $productB = $this->createMerchantProduct($shop, '1.500');
        $kadhia = $this->createSubmittedKadhiaWithLines($customer, $shop, [$productA, $productB]);
        $order = $this->createSubmittedOrderFromKadhia($customer, $shop, $kadhia, $slot);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/partially-accept', $shop->getId(), $order->getId()),
            ['rejected_merchant_product_ids' => []],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('NO_LINES_REJECTED', (string) $response->getContent());
    }

    public function testPartiallyAcceptOrderRejectsAllLines(): void
    {
        $merchant = $this->createUser('merchant-partial-all@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop);
        $customer = $this->createUser('customer-partial-all@example.test', ['ROLE_CUSTOMER']);
        $productA = $this->createMerchantProduct($shop, '2.000');
        $productB = $this->createMerchantProduct($shop, '1.500');
        $kadhia = $this->createSubmittedKadhiaWithLines($customer, $shop, [$productA, $productB]);
        $order = $this->createSubmittedOrderFromKadhia($customer, $shop, $kadhia, $slot);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/partially-accept', $shop->getId(), $order->getId()),
            ['rejected_merchant_product_ids' => [$productA->getId()->toRfc4122(), $productB->getId()->toRfc4122()]],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('USE_REJECT_ENDPOINT', (string) $response->getContent());
    }

    public function testPartiallyAcceptOrderRejectsUnknownLine(): void
    {
        $merchant = $this->createUser('merchant-partial-unknown@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop);
        $customer = $this->createUser('customer-partial-unknown@example.test', ['ROLE_CUSTOMER']);
        $productA = $this->createMerchantProduct($shop, '2.000');
        $productB = $this->createMerchantProduct($shop, '1.500');
        $kadhia = $this->createSubmittedKadhiaWithLines($customer, $shop, [$productA, $productB]);
        $order = $this->createSubmittedOrderFromKadhia($customer, $shop, $kadhia, $slot);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/partially-accept', $shop->getId(), $order->getId()),
            ['rejected_merchant_product_ids' => [Uuid::v4()->toRfc4122()]],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('ORDER_LINE_NOT_FOUND', (string) $response->getContent());
    }

    public function testPartiallyAcceptOrderRejectsLineFromAnotherOrder(): void
    {
        $merchant = $this->createUser('merchant-partial-other-order@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop);
        $customer = $this->createUser('customer-partial-other-order@example.test', ['ROLE_CUSTOMER']);
        $productA = $this->createMerchantProduct($shop, '2.000');
        $productB = $this->createMerchantProduct($shop, '1.500');
        $productC = $this->createMerchantProduct($shop, '3.000');
        $kadhia = $this->createSubmittedKadhiaWithLines($customer, $shop, [$productA, $productB]);
        $otherKadhia = $this->createSubmittedKadhiaWithLines($customer, $shop, [$productC]);
        $order = $this->createSubmittedOrderFromKadhia($customer, $shop, $kadhia, $slot);
        $this->createSubmittedOrderFromKadhia($customer, $shop, $otherKadhia, $slot);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/partially-accept', $shop->getId(), $order->getId()),
            ['rejected_merchant_product_ids' => [$productC->getId()->toRfc4122()]],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('ORDER_LINE_NOT_FOUND', (string) $response->getContent());
    }

    public function testPartiallyAcceptOrderRejectsDesynchronizedKadhiaLine(): void
    {
        $merchant = $this->createUser('merchant-partial-desync@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop);
        $customer = $this->createUser('customer-partial-desync@example.test', ['ROLE_CUSTOMER']);
        $productA = $this->createMerchantProduct($shop, '2.000');
        $productB = $this->createMerchantProduct($shop, '1.500');
        $kadhia = $this->createSubmittedKadhiaWithLines($customer, $shop, [$productA, $productB]);
        $order = $this->createSubmittedOrderFromKadhia($customer, $shop, $kadhia, $slot);

        foreach ($kadhia->getLines() as $line) {
            if ($line->getMerchantProduct()->getId()->equals($productB->getId())) {
                $kadhia->removeLine($line);
                break;
            }
        }
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/partially-accept', $shop->getId(), $order->getId()),
            ['rejected_merchant_product_ids' => [$productB->getId()->toRfc4122()]],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('KADHIA_LINE_NOT_FOUND', (string) $response->getContent());
    }

    #[DataProvider('nonSubmittedStatusProvider')]
    public function testPartiallyAcceptOrderInvalidTransitionReturns409(OrderStatus $status): void
    {
        $merchant = $this->createUser('merchant-partial-409-'.$status->value.'@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop);
        $customer = $this->createUser('customer-partial-409-'.$status->value.'@example.test', ['ROLE_CUSTOMER']);
        $productA = $this->createMerchantProduct($shop, '2.000');
        $productB = $this->createMerchantProduct($shop, '1.500');
        $kadhia = $this->createSubmittedKadhiaWithLines($customer, $shop, [$productA, $productB]);
        $order = $this->createSubmittedOrderFromKadhia($customer, $shop, $kadhia, $slot);
        $this->forceOrderStatus($order, $status);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/partially-accept', $shop->getId(), $order->getId()),
            ['rejected_merchant_product_ids' => [$productB->getId()->toRfc4122()]],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_SUBMITTED', (string) $response->getContent());
    }

    public function testPartiallyAcceptOrderFromAnotherShopReturns404(): void
    {
        $merchant = $this->createUser('merchant-partial-404@example.test', ['ROLE_MERCHANT']);
        $shop1 = $this->createShop($merchant);
        $shop2 = $this->createShop();
        $slot = $this->createPickupSlot($shop2);
        $customer = $this->createUser('customer-partial-404@example.test', ['ROLE_CUSTOMER']);
        $productA = $this->createMerchantProduct($shop2, '2.000');
        $productB = $this->createMerchantProduct($shop2, '1.500');
        $kadhia = $this->createSubmittedKadhiaWithLines($customer, $shop2, [$productA, $productB]);
        $orderInShop2 = $this->createSubmittedOrderFromKadhia($customer, $shop2, $kadhia, $slot);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/partially-accept', $shop1->getId(), $orderInShop2->getId()),
            ['rejected_merchant_product_ids' => [$productB->getId()->toRfc4122()]],
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPartiallyAcceptOrderWrongMerchantReturns403(): void
    {
        $merchantA = $this->createUser('merchant-a-partial@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-b-partial@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchantB);
        $slot = $this->createPickupSlot($shop);
        $customer = $this->createUser('customer-partial-forbidden@example.test', ['ROLE_CUSTOMER']);
        $productA = $this->createMerchantProduct($shop, '2.000');
        $productB = $this->createMerchantProduct($shop, '1.500');
        $kadhia = $this->createSubmittedKadhiaWithLines($customer, $shop, [$productA, $productB]);
        $order = $this->createSubmittedOrderFromKadhia($customer, $shop, $kadhia, $slot);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/partially-accept', $shop->getId(), $order->getId()),
            ['rejected_merchant_product_ids' => [$productB->getId()->toRfc4122()]],
            $merchantA,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testPartiallyAcceptOrderUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/partially-accept', $shop->getId(), Uuid::v4()->toRfc4122()),
            ['rejected_merchant_product_ids' => [Uuid::v4()->toRfc4122()]],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testPartiallyAcceptOrderCustomerRoleReturns403(): void
    {
        $customer = $this->createUser('customer-role-partial@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/partially-accept', $shop->getId(), Uuid::v4()->toRfc4122()),
            ['rejected_merchant_product_ids' => [Uuid::v4()->toRfc4122()]],
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

        $logs = $this->findStatusLogs($order);
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::Preparing, $logs[0]->getStatus());
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

    public function testStartPreparationWrongMerchantReturns403(): void
    {
        $merchantA = $this->createUser('merchant-a-prep-403@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-b-prep-403@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchantB);
        $customer = $this->createUser('customer-prep-403@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createAcceptedOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/start-preparation', $shop->getId(), $order->getId()),
            null,
            $merchantA,
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
        $productA = $this->createMerchantProduct($shop, '2.000', 'Semoule 1kg');
        $productB = $this->createMerchantProduct($shop, '1.500', 'Tomates conserve');
        $order = $this->createPreparingOrder($customer, $shop);
        $lineA = $this->addOrderLine($order, $productA, quantity: 1, unitPriceTnd: '2.000');
        $lineB = $this->addOrderLine($order, $productB, quantity: 2, unitPriceTnd: '1.500');
        $lineA->markPrepared(true);
        $lineB->markPrepared(true);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/mark-ready', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('ready', $payload['status']);

        $logs = $this->findStatusLogs($order);
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::Ready, $logs[0]->getStatus());

        $historyResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s/status-history', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $historyResponse->getStatusCode());

        $historyPayload = $this->decodeJson($historyResponse);
        self::assertSame('ready', $historyPayload['transitions'][0]['status']);
    }

    public function testMarkReadyRejectsPartiallyPreparedLines(): void
    {
        $merchant = $this->createUser('merchant-ready-partial@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-ready-partial@example.test', ['ROLE_CUSTOMER']);
        $productA = $this->createMerchantProduct($shop, '2.000', 'Produit prêt');
        $productB = $this->createMerchantProduct($shop, '3.000', 'Produit non prêt');
        $order = $this->createPreparingOrder($customer, $shop);
        $lineA = $this->addOrderLine($order, $productA, quantity: 1, unitPriceTnd: '2.000');
        $this->addOrderLine($order, $productB, quantity: 1, unitPriceTnd: '3.000');
        $lineA->markPrepared(true);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/mark-ready', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_LINES_NOT_FULLY_PREPARED', (string) $response->getContent());
    }

    public function testMarkReadyRejectsWhenNoLineIsPrepared(): void
    {
        $merchant = $this->createUser('merchant-ready-none-prepared@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-ready-none-prepared@example.test', ['ROLE_CUSTOMER']);
        $productA = $this->createMerchantProduct($shop, '2.000', 'Produit A');
        $productB = $this->createMerchantProduct($shop, '3.000', 'Produit B');
        $order = $this->createPreparingOrder($customer, $shop);
        $this->addOrderLine($order, $productA, quantity: 1, unitPriceTnd: '2.000');
        $this->addOrderLine($order, $productB, quantity: 1, unitPriceTnd: '3.000');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/mark-ready', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_LINES_NOT_FULLY_PREPARED', (string) $response->getContent());
    }

    public function testMarkReadyRejectsPreparingOrderWithoutLines(): void
    {
        $merchant = $this->createUser('merchant-ready-no-lines@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-ready-no-lines@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createPreparingOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/mark-ready', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_LINES_NOT_FULLY_PREPARED', (string) $response->getContent());
    }

    #[DataProvider('nonPreparingStatusProvider')]
    public function testMarkReadyRejectsNonPreparingOrders(OrderStatus $status): void
    {
        $merchant = $this->createUser('merchant-ready-status-'.$status->value.'@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-ready-status-'.$status->value.'@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createOrderWithStatus($customer, $shop, $status);

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

    public function testMarkReadyWrongMerchantReturns403(): void
    {
        $merchantA = $this->createUser('merchant-a-ready-403@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-b-ready-403@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchantB);
        $customer = $this->createUser('customer-ready-403@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop, '1.000');
        $order = $this->createPreparingOrder($customer, $shop);
        $line = $this->addOrderLine($order, $product, quantity: 1, unitPriceTnd: '1.000');
        $line->markPrepared(true);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/mark-ready', $shop->getId(), $order->getId()),
            null,
            $merchantA,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // ---------------------------------------------------------------------------
    // PATCH /api/merchant/stores/{storeId}/orders/{orderId}/lines/{merchantProductId}/preparation
    // ---------------------------------------------------------------------------

    public function testPrepareOrderLineHappyPathPersistsAndIsVisibleInDetail(): void
    {
        $merchant = $this->createUser('merchant-line-prep@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-line-prep@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop, '3.200', 'Harissa 135g');
        $order = $this->createPreparingOrder($customer, $shop);
        $this->addOrderLine($order, $product, quantity: 3, unitPriceTnd: '3.200');

        $response = $this->requestJson(
            'PATCH',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/lines/%s/preparation',
                $shop->getId(),
                $order->getId(),
                $product->getId(),
            ),
            ['prepared' => true],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame($order->getId()->toRfc4122(), $payload['id']);
        self::assertSame('preparing', $payload['status']);
        self::assertCount(1, $payload['lines']);
        self::assertSame($product->getId()->toRfc4122(), $payload['lines'][0]['merchant_product_id']);
        self::assertTrue($payload['lines'][0]['prepared']);

        $this->entityManager->clear();

        $detailResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s', $shop->getId(), $order->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $detailResponse->getStatusCode());

        $detailPayload = $this->decodeJson($detailResponse);
        self::assertTrue($detailPayload['lines'][0]['prepared']);
    }

    public function testPrepareOrderLineCanUnsetPrepared(): void
    {
        $merchant = $this->createUser('merchant-line-unprep@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-line-unprep@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop, '1.400', 'Eau minérale 1.5L');
        $order = $this->createPreparingOrder($customer, $shop);
        $line = $this->addOrderLine($order, $product, quantity: 2, unitPriceTnd: '1.400');
        $line->markPrepared(true);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/lines/%s/preparation',
                $shop->getId(),
                $order->getId(),
                $product->getId(),
            ),
            ['prepared' => false],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertFalse($payload['lines'][0]['prepared']);

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(OrderLine::class)->find($line->getId());
        self::assertNotNull($updated);
        self::assertFalse($updated->isPrepared());
    }

    public function testPrepareOrderLineWrongMerchantReturns403(): void
    {
        $merchantA = $this->createUser('merchant-a-line-prep@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-b-line-prep@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchantB);
        $customer = $this->createUser('customer-line-prep-forbidden@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop, '4.000');
        $order = $this->createPreparingOrder($customer, $shop);
        $this->addOrderLine($order, $product, quantity: 1, unitPriceTnd: '4.000');

        $response = $this->requestJson(
            'PATCH',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/lines/%s/preparation',
                $shop->getId(),
                $order->getId(),
                $product->getId(),
            ),
            ['prepared' => true],
            $merchantA,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testPrepareOrderLineFromAnotherShopReturns404(): void
    {
        $merchant = $this->createUser('merchant-line-prep-404@example.test', ['ROLE_MERCHANT']);
        $shop1 = $this->createShop($merchant);
        $shop2 = $this->createShop();
        $customer = $this->createUser('customer-line-prep-404@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop2, '4.000');
        $orderInShop2 = $this->createPreparingOrder($customer, $shop2);
        $this->addOrderLine($orderInShop2, $product, quantity: 1, unitPriceTnd: '4.000');

        $response = $this->requestJson(
            'PATCH',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/lines/%s/preparation',
                $shop1->getId(),
                $orderInShop2->getId(),
                $product->getId(),
            ),
            ['prepared' => true],
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPrepareOrderLineCustomerRoleReturns403(): void
    {
        $customer = $this->createUser('customer-role-line-prep@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'PATCH',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/lines/%s/preparation',
                $shop->getId(),
                Uuid::v4()->toRfc4122(),
                Uuid::v4()->toRfc4122(),
            ),
            ['prepared' => true],
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testPrepareOrderLineUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'PATCH',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/lines/%s/preparation',
                $shop->getId(),
                Uuid::v4()->toRfc4122(),
                Uuid::v4()->toRfc4122(),
            ),
            ['prepared' => true],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testPrepareOrderLineMissingPreparedFieldReturns422(): void
    {
        $merchant = $this->createUser('merchant-line-prep-missing-field@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-line-prep-missing-field@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop, '2.000');
        $order = $this->createPreparingOrder($customer, $shop);
        $this->addOrderLine($order, $product, quantity: 1, unitPriceTnd: '2.000');

        $response = $this->requestJson(
            'PATCH',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/lines/%s/preparation',
                $shop->getId(),
                $order->getId(),
                $product->getId(),
            ),
            [],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('prepared', (string) $response->getContent());
    }

    #[DataProvider('nonPreparingStatusProvider')]
    public function testPrepareOrderLineRejectsNonPreparingOrders(OrderStatus $status): void
    {
        $merchant = $this->createUser('merchant-line-prep-status-'.$status->value.'@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-line-prep-status-'.$status->value.'@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop, '2.000');
        $order = $this->createOrderWithStatus($customer, $shop, $status);
        $this->addOrderLine($order, $product, quantity: 1, unitPriceTnd: '2.000');

        $response = $this->requestJson(
            'PATCH',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/lines/%s/preparation',
                $shop->getId(),
                $order->getId(),
                $product->getId(),
            ),
            ['prepared' => true],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_PREPARING', (string) $response->getContent());
    }

    public function testPrepareOrderLineUnknownLineReturns404(): void
    {
        $merchant = $this->createUser('merchant-line-prep-unknown@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-line-prep-unknown@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop, '2.000');
        $order = $this->createPreparingOrder($customer, $shop);
        $this->addOrderLine($order, $product, quantity: 1, unitPriceTnd: '2.000');

        $response = $this->requestJson(
            'PATCH',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/lines/%s/preparation',
                $shop->getId(),
                $order->getId(),
                Uuid::v4()->toRfc4122(),
            ),
            ['prepared' => true],
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('ORDER_LINE_NOT_FOUND', (string) $response->getContent());
    }

    public function testPrepareOrderLineFromAnotherOrderReturns404(): void
    {
        $merchant = $this->createUser('merchant-line-prep-other-order@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-line-prep-other-order@example.test', ['ROLE_CUSTOMER']);
        $product1 = $this->createMerchantProduct($shop, '2.000');
        $product2 = $this->createMerchantProduct($shop, '3.000');
        $order1 = $this->createPreparingOrder($customer, $shop);
        $order2 = $this->createPreparingOrder($customer, $shop);
        $this->addOrderLine($order1, $product1, quantity: 1, unitPriceTnd: '2.000');
        $this->addOrderLine($order2, $product2, quantity: 1, unitPriceTnd: '3.000');

        $response = $this->requestJson(
            'PATCH',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/lines/%s/preparation',
                $shop->getId(),
                $order1->getId(),
                $product2->getId(),
            ),
            ['prepared' => true],
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('ORDER_LINE_NOT_FOUND', (string) $response->getContent());
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * @return iterable<string, array{OrderStatus}>
     */
    public static function nonPreparingStatusProvider(): iterable
    {
        yield 'draft' => [OrderStatus::Draft];
        yield 'submitted' => [OrderStatus::Submitted];
        yield 'accepted' => [OrderStatus::Accepted];
        yield 'partially_accepted' => [OrderStatus::PartiallyAccepted];
        yield 'rejected' => [OrderStatus::Rejected];
        yield 'ready' => [OrderStatus::Ready];
        yield 'pickup_pending' => [OrderStatus::PickupPending];
        yield 'completed' => [OrderStatus::Completed];
        yield 'cancelled' => [OrderStatus::Cancelled];
    }

    /**
     * @return iterable<string, array{OrderStatus}>
     */
    public static function nonSubmittedStatusProvider(): iterable
    {
        yield 'draft' => [OrderStatus::Draft];
        yield 'accepted' => [OrderStatus::Accepted];
        yield 'partially_accepted' => [OrderStatus::PartiallyAccepted];
        yield 'rejected' => [OrderStatus::Rejected];
        yield 'preparing' => [OrderStatus::Preparing];
        yield 'ready' => [OrderStatus::Ready];
        yield 'pickup_pending' => [OrderStatus::PickupPending];
        yield 'completed' => [OrderStatus::Completed];
        yield 'cancelled' => [OrderStatus::Cancelled];
    }

    private function createOrderWithStatus(User $customer, Shop $shop, OrderStatus $status): Order
    {
        return match ($status) {
            OrderStatus::Draft => $this->createDraftOrder($customer, $shop),
            OrderStatus::Submitted => $this->createSubmittedOrder($customer, $shop),
            OrderStatus::Accepted => $this->createAcceptedOrder($customer, $shop),
            OrderStatus::PartiallyAccepted => $this->createPartiallyAcceptedOrder($customer, $shop),
            OrderStatus::Rejected => $this->createRejectedOrder($customer, $shop),
            OrderStatus::Preparing => $this->createPreparingOrder($customer, $shop),
            OrderStatus::Ready => $this->createReadyOrder($customer, $shop),
            OrderStatus::PickupPending => $this->createPickupPendingOrder($customer, $shop),
            OrderStatus::Completed => $this->createCompletedOrder($customer, $shop),
            OrderStatus::Cancelled => $this->createCancelledOrder($customer, $shop),
        };
    }

    private function createDraftOrder(User $customer, Shop $shop): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

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

    private function createRejectedOrder(User $customer, Shop $shop): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->submit();
        $order->reject('Rupture de stock');
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createPartiallyAcceptedOrder(User $customer, Shop $shop): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->submit();
        $order->partiallyAccept();
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createReadyOrder(User $customer, Shop $shop): Order
    {
        $product = $this->createMerchantProduct($shop, '1.000');
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->submit();
        $order->accept();
        $order->startPreparing();

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd('1.000')
            ->setLineTotalTnd('1.000')
            ->markPrepared(true);
        $order->addLine($line);
        $order->recomputeTotal();
        $order->markReady();

        $this->entityManager->persist($order);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $order;
    }

    private function createPickupPendingOrder(User $customer, Shop $shop): Order
    {
        $order = $this->createReadyOrder($customer, $shop);
        $order->startPickup();
        $this->entityManager->flush();

        return $order;
    }

    private function createCompletedOrder(User $customer, Shop $shop): Order
    {
        $order = $this->createReadyOrder($customer, $shop);
        $order->startPickup();
        $order->complete();
        $this->entityManager->flush();

        return $order;
    }

    private function createCancelledOrder(User $customer, Shop $shop): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->submit();
        $order->cancel();
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createPickupSlot(
        Shop $shop,
        string $startsAtModifier = '+1 hour',
        string $endsAtModifier = '+2 hours',
    ): PickupSlot {
        $now = new \DateTimeImmutable();
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify($startsAtModifier))
            ->setEndsAt($now->modify($endsAtModifier))
            ->setCapacity(5);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }

    /**
     * @param list<MerchantProduct> $products
     */
    private function createSubmittedKadhiaWithLines(User $customer, Shop $shop, array $products): Kadhia
    {
        $kadhia = (new Kadhia())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setStatus(KadhiaStatus::Submitted);
        $this->entityManager->persist($kadhia);

        foreach ($products as $product) {
            $line = (new KadhiaLine())
                ->setMerchantProduct($product)
                ->setQuantity(1)
                ->setUnitPriceTnd($product->getPriceTnd());
            $kadhia->addLine($line);
            $this->entityManager->persist($line);
        }

        $this->entityManager->flush();

        return $kadhia;
    }

    private function createSubmittedOrderFromKadhia(User $customer, Shop $shop, Kadhia $kadhia, PickupSlot $slot): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setKadhia($kadhia)
            ->setPickupSlot($slot);
        $order->submit();
        $this->entityManager->persist($order);

        foreach ($kadhia->getLines() as $kadhiaLine) {
            $this->addOrderLineWithoutFlush(
                $order,
                $kadhiaLine->getMerchantProduct(),
                $kadhiaLine->getQuantity(),
                $kadhiaLine->getUnitPriceTnd(),
            );
        }

        $order->recomputeTotal();
        $this->entityManager->flush();

        return $order;
    }

    private function forceOrderStatus(Order $order, OrderStatus $status): void
    {
        $reflection = new \ReflectionProperty(Order::class, 'status');
        $reflection->setValue($order, $status);
    }

    private function createMerchantProduct(Shop $shop, string $priceTnd, string $nameFr = 'Produit test'): MerchantProduct
    {
        $id = Uuid::v4()->toRfc4122();
        $brand = (new Brand())
            ->setCanonicalName('Brand '.$id)
            ->setSlug('brand-merchant-order-'.$id);
        $category = (new Category())
            ->setNameFr('Catégorie '.$id)
            ->setSlug('categorie-merchant-order-'.$id);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setStatus(ProductReferenceStatus::Approved);
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd($priceTnd);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }

    private function addOrderLine(Order $order, MerchantProduct $product, int $quantity, string $unitPriceTnd): OrderLine
    {
        $line = $this->addOrderLineWithoutFlush($order, $product, $quantity, $unitPriceTnd);
        $order->recomputeTotal();
        $this->entityManager->flush();

        return $line;
    }

    private function addOrderLineWithoutFlush(Order $order, MerchantProduct $product, int $quantity, string $unitPriceTnd): OrderLine
    {
        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity($quantity)
            ->setUnitPriceTnd($unitPriceTnd)
            ->setLineTotalTnd(bcmul((string) $quantity, $unitPriceTnd, 3));

        $order->addLine($line);
        $this->entityManager->persist($line);

        return $line;
    }

    /**
     * @return list<OrderStatusLog>
     */
    private function findStatusLogs(Order $order): array
    {
        return $this->entityManager->getRepository(OrderStatusLog::class)->findBy(['order' => $order], ['createdAt' => 'ASC']);
    }
}
