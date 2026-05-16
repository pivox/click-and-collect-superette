<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\PickupSession;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use Symfony\Component\Uid\Uuid;

final class CustomerOrderStatusApiTest extends FunctionalApiTestCase
{
    // --- GET /api/me/orders/{orderId}/status ---

    public function testOwnerCanFetchStatusForSubmittedOrder(): void
    {
        $customer = $this->createUser('status-owner@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);
        $order = $this->createSubmittedOrder($customer, $shop, $product);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', $order->getId()->toRfc4122()),
            user: $customer,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testResponseContainsAllExpectedFields(): void
    {
        $customer = $this->createUser('status-fields@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);
        $order = $this->createSubmittedOrder($customer, $shop, $product);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', $order->getId()->toRfc4122()),
            user: $customer,
        );

        $payload = $this->decodeJson($response);
        self::assertArrayHasKey('order_id', $payload);
        self::assertArrayHasKey('status', $payload);
        self::assertArrayHasKey('status_label_fr', $payload);
        self::assertArrayHasKey('status_label_ar', $payload);
        self::assertArrayHasKey('updated_at', $payload);
        self::assertArrayHasKey('pickup_session', $payload);
        self::assertSame($order->getId()->toRfc4122(), $payload['order_id']);
        self::assertSame('submitted', $payload['status']);
        self::assertNotEmpty($payload['status_label_fr']);
        self::assertNotEmpty($payload['status_label_ar']);
    }

    public function testReadyOrderWithPickupSessionReturnsExistsTrue(): void
    {
        $customer = $this->createUser('status-ready-session@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $this->entityManager->persist(new PickupSession($order));
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', $order->getId()->toRfc4122()),
            user: $customer,
        );

        $payload = $this->decodeJson($response);
        self::assertTrue($payload['pickup_session']['exists']);
    }

    public function testPickupSessionNotYetScannedReturnsIsScannedFalse(): void
    {
        $customer = $this->createUser('status-not-scanned@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $this->entityManager->persist(new PickupSession($order));
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', $order->getId()->toRfc4122()),
            user: $customer,
        );

        $payload = $this->decodeJson($response);
        self::assertFalse($payload['pickup_session']['is_scanned']);
    }

    public function testPickupSessionAfterScanReturnsIsScannedTrue(): void
    {
        $customer = $this->createUser('status-scanned@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $session = new PickupSession($order);
        $session->scan();
        $order->startPickup();
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', $order->getId()->toRfc4122()),
            user: $customer,
        );

        $payload = $this->decodeJson($response);
        self::assertSame('pickup_pending', $payload['status']);
        self::assertTrue($payload['pickup_session']['is_scanned']);
        self::assertFalse($payload['pickup_session']['merchant_confirmed']);
    }

    public function testAfterMerchantConfirmReportsMerchantConfirmedTrue(): void
    {
        $customer = $this->createUser('status-merch-confirm@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $session = new PickupSession($order);
        $session->scan();
        $order->startPickup();
        $session->confirmByMerchant();
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', $order->getId()->toRfc4122()),
            user: $customer,
        );

        $payload = $this->decodeJson($response);
        self::assertTrue($payload['pickup_session']['merchant_confirmed']);
        self::assertFalse($payload['pickup_session']['customer_confirmed']);
        self::assertFalse($payload['pickup_session']['is_used']);
    }

    public function testAfterBothConfirmationsOrderIsCompletedAndSessionIsUsed(): void
    {
        $customer = $this->createUser('status-both-confirm@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $session = new PickupSession($order);
        $session->scan();
        $order->startPickup();
        $session->confirmByMerchant();
        $session->confirmByCustomer();
        $order->complete();
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', $order->getId()->toRfc4122()),
            user: $customer,
        );

        $payload = $this->decodeJson($response);
        self::assertSame('completed', $payload['status']);
        self::assertTrue($payload['pickup_session']['customer_confirmed']);
        self::assertTrue($payload['pickup_session']['is_used']);
        self::assertFalse($payload['pickup_session']['force_completed_by_merchant']);
    }

    public function testAfterForceCompletionByMerchantReportsCorrectly(): void
    {
        $customer = $this->createUser('status-force@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $session = new PickupSession($order);
        $session->scan();
        $order->startPickup();
        $session->forceCompleteByMerchant('test note');
        $order->complete();
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', $order->getId()->toRfc4122()),
            user: $customer,
        );

        $payload = $this->decodeJson($response);
        self::assertSame('completed', $payload['status']);
        self::assertTrue($payload['pickup_session']['force_completed_by_merchant']);
        self::assertFalse($payload['pickup_session']['customer_confirmed']);
        self::assertTrue($payload['pickup_session']['is_used']);
    }

    public function testOrderWithoutPickupSessionReturnsExistsFalse(): void
    {
        $customer = $this->createUser('status-no-session@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);
        $order = $this->createSubmittedOrder($customer, $shop, $product);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', $order->getId()->toRfc4122()),
            user: $customer,
        );

        $payload = $this->decodeJson($response);
        $sessionData = $payload['pickup_session'];
        self::assertFalse($sessionData['exists']);
        self::assertFalse($sessionData['is_scanned']);
        self::assertFalse($sessionData['merchant_confirmed']);
        self::assertFalse($sessionData['customer_confirmed']);
        self::assertFalse($sessionData['is_used']);
        self::assertFalse($sessionData['force_completed_by_merchant']);
    }

    public function testQrTokenIsAbsentFromResponse(): void
    {
        $customer = $this->createUser('status-no-token@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $this->entityManager->persist(new PickupSession($order));
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', $order->getId()->toRfc4122()),
            user: $customer,
        );

        $payload = $this->decodeJson($response);
        self::assertArrayNotHasKey('token', $payload);
        self::assertArrayNotHasKey('qr_payload', $payload);
        self::assertArrayNotHasKey('token', $payload['pickup_session']);
        self::assertArrayNotHasKey('qr_payload', $payload['pickup_session']);
    }

    public function testOrderLinesAreAbsentFromResponse(): void
    {
        $customer = $this->createUser('status-no-lines@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);
        $order = $this->createSubmittedOrder($customer, $shop, $product);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', $order->getId()->toRfc4122()),
            user: $customer,
        );

        $payload = $this->decodeJson($response);
        self::assertArrayNotHasKey('lines', $payload);
    }

    public function testNonOwnerCustomerReturns404(): void
    {
        $customerA = $this->createUser('status-owner-a@example.test', ['ROLE_CUSTOMER']);
        $customerB = $this->createUser('status-owner-b@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop);
        $order = $this->createSubmittedOrder($customerA, $shop, $product);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', $order->getId()->toRfc4122()),
            user: $customerB,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUnknownOrderReturns404(): void
    {
        $customer = $this->createUser('status-unknown@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', Uuid::v4()->toRfc4122()),
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testAnonymousReturns401(): void
    {
        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', Uuid::v4()->toRfc4122()),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testMerchantRoleReturns403(): void
    {
        $merchant = $this->createUser('status-merchant-role@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', Uuid::v4()->toRfc4122()),
            user: $merchant,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // --- Fixtures ---

    private function createSubmittedOrder(User $customer, Shop $shop, MerchantProduct $product): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->submit();

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd($product->getPriceTnd())
            ->setLineTotalTnd($product->getPriceTnd());
        $order->addLine($line);
        $order->recomputeTotal();

        $this->entityManager->persist($order);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $order;
    }

    private function createReadyOrder(User $customer, Shop $shop, MerchantProduct $product): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->submit();
        $order->accept();
        $order->startPreparing();

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd($product->getPriceTnd())
            ->setLineTotalTnd($product->getPriceTnd())
            ->markPrepared(true);
        $order->addLine($line);
        $order->recomputeTotal();
        $order->markReady();

        $this->entityManager->persist($order);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $order;
    }

    private function createMerchantProduct(Shop $shop): MerchantProduct
    {
        $id = Uuid::v4()->toRfc4122();
        $brand = (new Brand())
            ->setCanonicalName('Brand Status '.$id)
            ->setSlug('brand-status-'.$id);
        $category = (new Category())
            ->setNameFr('Cat Status '.$id)
            ->setSlug('cat-status-'.$id);
        $ref = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit Status')
            ->setStatus(ProductReferenceStatus::Approved);
        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd('1.500');

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ref);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }
}
