<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\OrderStatusLog;
use App\Entity\PickupSession;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\ProductReferenceStatus;
use Symfony\Component\Uid\Uuid;

final class CustomerPickupSessionConfirmApiTest extends FunctionalApiTestCase
{
    public function testCustomerOwnerConfirmsScannedSession(): void
    {
        $customer = $this->createUser('cust-confirm@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-confirm@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        [$order, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame($pickupSession->getId()->toRfc4122(), $payload['id']);
        self::assertSame($order->getId()->toRfc4122(), $payload['order_id']);
        self::assertSame('pickup_pending', $payload['order_status']);
        self::assertNotEmpty($payload['scanned_at']);
        self::assertNull($payload['merchant_confirmed_at']);
        self::assertNotEmpty($payload['customer_confirmed_at']);
        self::assertFalse($payload['is_used']);
        self::assertFalse($payload['is_completed']);
    }

    public function testCustomerConfirmedAtIsSetAfterConfirm(): void
    {
        $customer = $this->createUser('cust-confirmed-at@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-confirmed-at@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customer,
        );

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(PickupSession::class)->find($pickupSession->getId());
        self::assertNotNull($updated);
        self::assertNotNull($updated->getCustomerConfirmedAt());
    }

    public function testOrderCompletesWhenMerchantAlreadyConfirmed(): void
    {
        $customer = $this->createUser('cust-after-merch@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-before-cust@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        [$order, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);
        $pickupSession->confirmByMerchant(new \DateTimeImmutable('-5 minutes'));
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('completed', $payload['order_status']);
        self::assertNotEmpty($payload['merchant_confirmed_at']);
        self::assertNotEmpty($payload['customer_confirmed_at']);
        self::assertTrue($payload['is_used']);
        self::assertTrue($payload['is_completed']);

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::Completed, $updatedOrder->getStatus());
    }

    public function testIsUsedTrueWhenMerchantAlreadyConfirmed(): void
    {
        $customer = $this->createUser('cust-is-used@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-is-used@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);
        $pickupSession->confirmByMerchant(new \DateTimeImmutable('-5 minutes'));
        $this->entityManager->flush();

        $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customer,
        );

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(PickupSession::class)->find($pickupSession->getId());
        self::assertNotNull($updated);
        self::assertTrue($updated->isUsed());
    }

    public function testCompletedLogCreatedWhenMerchantAlreadyConfirmed(): void
    {
        $customer = $this->createUser('cust-log@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-log@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        [$order, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);
        $pickupSession->confirmByMerchant(new \DateTimeImmutable('-5 minutes'));
        $this->entityManager->flush();

        $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customer,
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        $logs = $this->entityManager->getRepository(OrderStatusLog::class)->findBy(['order' => $updatedOrder]);
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::Completed, $logs[0]->getStatus());
    }

    public function testOrderStaysPickupPendingWhenMerchantNotYetConfirmed(): void
    {
        $customer = $this->createUser('cust-pending@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-pending@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        [$order, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('pickup_pending', $payload['order_status']);
        self::assertFalse($payload['is_used']);
        self::assertFalse($payload['is_completed']);

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::PickupPending, $updatedOrder->getStatus());

        $logs = $this->entityManager->getRepository(OrderStatusLog::class)->findBy(['order' => $updatedOrder]);
        self::assertCount(0, $logs);
    }

    public function testIdempotentConfirmationDoesNotDuplicateCompletedLog(): void
    {
        $customer = $this->createUser('cust-idempotent@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-idempotent@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        [$order, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);
        $pickupSession->confirmByMerchant(new \DateTimeImmutable('-5 minutes'));
        $this->entityManager->flush();

        $firstResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customer,
        );
        self::assertSame(200, $firstResponse->getStatusCode());
        $firstPayload = $this->decodeJson($firstResponse);

        // Session is now used → second call should return 409, not a duplicate log
        $secondResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customer,
        );
        self::assertSame(409, $secondResponse->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_ALREADY_USED', (string) $secondResponse->getContent());

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        $logs = $this->entityManager->getRepository(OrderStatusLog::class)->findBy(['order' => $updatedOrder]);
        self::assertCount(1, $logs);
        self::assertNotEmpty($firstPayload['customer_confirmed_at']);
    }

    public function testAnonymousReturns401(): void
    {
        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', Uuid::v4()->toRfc4122()),
            [],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testMerchantRoleReturns403(): void
    {
        $merchant = $this->createUser('merch-role-forbidden@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', Uuid::v4()->toRfc4122()),
            [],
            $merchant,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testNonOwnerCustomerReturns404(): void
    {
        $customerA = $this->createUser('cust-owner-a@example.test', ['ROLE_CUSTOMER']);
        $customerB = $this->createUser('cust-owner-b@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createScannedPickupPendingSession($customerA, $shop, $product);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customerB,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_NOT_FOUND', (string) $response->getContent());
    }

    public function testUnknownSessionReturns404(): void
    {
        $customer = $this->createUser('cust-unknown@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', Uuid::v4()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_NOT_FOUND', (string) $response->getContent());
    }

    public function testNotScannedSessionReturns409(): void
    {
        $customer = $this->createUser('cust-not-scanned@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-not-scanned@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $order->startPickup();
        $pickupSession = new PickupSession($order);
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_NOT_SCANNED', (string) $response->getContent());
    }

    public function testExpiredSessionReturns409(): void
    {
        $customer = $this->createUser('cust-expired@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-expired@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);
        $this->setPrivateProperty($pickupSession, 'expiresAt', new \DateTimeImmutable('-1 minute'));
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_EXPIRED', (string) $response->getContent());
    }

    public function testOrderNotPickupPendingReturns409(): void
    {
        $customer = $this->createUser('cust-not-pending@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-not-pending@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $pickupSession = new PickupSession($order);
        $pickupSession->scan();
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_PICKUP_PENDING', (string) $response->getContent());
    }

    public function testAlreadyUsedSessionReturns409(): void
    {
        $customer = $this->createUser('cust-already-used@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-already-used@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);
        $this->setPrivateProperty($pickupSession, 'used', true);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_ALREADY_USED', (string) $response->getContent());
    }

    public function testCompletedOrderReturns409(): void
    {
        $customer = $this->createUser('cust-completed-order@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-completed-order@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        [$order, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);
        $order->complete();
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_ALREADY_USED', (string) $response->getContent());
    }

    /**
     * @return array{0: Order, 1: PickupSession}
     */
    private function createScannedPickupPendingSession(User $customer, Shop $shop, MerchantProduct $product): array
    {
        $order = $this->createReadyOrder($customer, $shop, $product);
        $pickupSession = new PickupSession($order);
        $pickupSession->scan(new \DateTimeImmutable('-30 minutes'));
        $order->startPickup();

        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        return [$order, $pickupSession];
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
            ->setQuantity(2)
            ->setUnitPriceTnd($product->getPriceTnd())
            ->setLineTotalTnd(bcmul('2', $product->getPriceTnd(), 3))
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
            ->setCanonicalName('Brand CustConf '.$id)
            ->setSlug('brand-cust-conf-'.$id);
        $category = (new Category())
            ->setNameFr('Catégorie CustConf '.$id)
            ->setSlug('cat-cust-conf-'.$id);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit cust confirm')
            ->setStatus(ProductReferenceStatus::Approved);
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('2.800');

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }
}
