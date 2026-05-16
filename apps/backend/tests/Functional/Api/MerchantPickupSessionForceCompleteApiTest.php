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

final class MerchantPickupSessionForceCompleteApiTest extends FunctionalApiTestCase
{
    // --- Happy path ---

    public function testMerchantOwnerForceCompletesAfterDelayAndMerchantConfirm(): void
    {
        $merchant = $this->createUser('merchant-fc-happy@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-happy@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [$order, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Client parti sans confirmer sur son téléphone.'],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame($pickupSession->getId()->toRfc4122(), $payload['id']);
        self::assertSame($order->getId()->toRfc4122(), $payload['order_id']);
        self::assertSame('completed', $payload['order_status']);
        self::assertNotEmpty($payload['scanned_at']);
        self::assertNotEmpty($payload['merchant_confirmed_at']);
        self::assertNull($payload['customer_confirmed_at']);
        self::assertTrue($payload['is_used']);
        self::assertTrue($payload['is_completed']);
        self::assertTrue($payload['force_completed_by_merchant']);
        self::assertSame('Client parti sans confirmer sur son téléphone.', $payload['force_note']);
    }

    public function testOrderPassesToCompleted(): void
    {
        $merchant = $this->createUser('merchant-fc-status@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-status@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [$order, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'No response from customer.'],
            $merchant,
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::Completed, $updatedOrder->getStatus());
    }

    public function testPickupSessionIsUsedAfterForceComplete(): void
    {
        $merchant = $this->createUser('merchant-fc-used@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-used@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Client absent.'],
            $merchant,
        );

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(PickupSession::class)->find($pickupSession->getId());
        self::assertNotNull($updated);
        self::assertTrue($updated->isUsed());
    }

    public function testForceCompletedByMerchantFlagIsSet(): void
    {
        $merchant = $this->createUser('merchant-fc-flag@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-flag@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Timeout.'],
            $merchant,
        );

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(PickupSession::class)->find($pickupSession->getId());
        self::assertNotNull($updated);
        self::assertTrue($updated->isForceCompletedByMerchant());
    }

    public function testForceNoteIsPersisted(): void
    {
        $merchant = $this->createUser('merchant-fc-note@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-note@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Client a quitté la supérette.'],
            $merchant,
        );

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(PickupSession::class)->find($pickupSession->getId());
        self::assertNotNull($updated);
        self::assertSame('Client a quitté la supérette.', $updated->getForceNote());
    }

    public function testCustomerConfirmedAtRemainsNull(): void
    {
        $merchant = $this->createUser('merchant-fc-cust-null@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-cust-null@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Forçage.'],
            $merchant,
        );

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(PickupSession::class)->find($pickupSession->getId());
        self::assertNotNull($updated);
        self::assertNull($updated->getCustomerConfirmedAt());
    }

    public function testOrderStatusLogCompletedIsCreatedWithNote(): void
    {
        $merchant = $this->createUser('merchant-fc-log@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-log@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [$order, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Confirmation manquante.'],
            $merchant,
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);

        $logs = $this->entityManager->getRepository(OrderStatusLog::class)->findBy([
            'order' => $updatedOrder,
            'status' => OrderStatus::Completed,
        ]);
        self::assertCount(1, $logs);
        self::assertNotNull($logs[0]->getNote());
        self::assertStringContainsString('Confirmation manquante.', $logs[0]->getNote());
    }

    // --- Auth / access errors ---

    public function testAnonymousReturns401(): void
    {
        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', Uuid::v4()->toRfc4122()),
            ['note' => 'Test.'],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testCustomerRoleReturns403(): void
    {
        $customer = $this->createUser('customer-fc-role@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', Uuid::v4()->toRfc4122()),
            ['note' => 'Test.'],
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testWrongMerchantReturns403(): void
    {
        $merchantA = $this->createUser('merchant-fc-a@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-fc-b@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchantB);
        $customer = $this->createUser('customer-fc-forbidden@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Test.'],
            $merchantA,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // --- Business rule errors ---

    public function testUnknownSessionReturns404(): void
    {
        $merchant = $this->createUser('merchant-fc-unknown@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', Uuid::v4()->toRfc4122()),
            ['note' => 'Test.'],
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_NOT_FOUND', (string) $response->getContent());
    }

    public function testNotScannedSessionReturns409(): void
    {
        $merchant = $this->createUser('merchant-fc-not-scanned@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-not-scanned@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $order->startPickup();
        $pickupSession = new PickupSession($order);
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Test.'],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_NOT_SCANNED', (string) $response->getContent());
    }

    public function testOrderNotPickupPendingReturns409(): void
    {
        $merchant = $this->createUser('merchant-fc-not-pending@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-not-pending@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $pickupSession = new PickupSession($order);
        $pickupSession->scan(new \DateTimeImmutable('-10 minutes'));
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Test.'],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_PICKUP_PENDING', (string) $response->getContent());
    }

    public function testAlreadyUsedSessionReturns409(): void
    {
        $merchant = $this->createUser('merchant-fc-used-409@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-used-409@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);
        $this->setPrivateProperty($pickupSession, 'used', true);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Test.'],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_ALREADY_USED', (string) $response->getContent());
    }

    public function testCompletedOrderReturns409(): void
    {
        $merchant = $this->createUser('merchant-fc-completed@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-completed@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [$order, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);
        $order->complete();
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Test.'],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_ALREADY_USED', (string) $response->getContent());
    }

    public function testCustomerAlreadyConfirmedReturns409(): void
    {
        $merchant = $this->createUser('merchant-fc-cust-confirmed@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-cust-confirmed@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);
        $this->setPrivateProperty($pickupSession, 'customerConfirmedAt', new \DateTimeImmutable('-3 minutes'));
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Test.'],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_ALREADY_CUSTOMER_CONFIRMED', (string) $response->getContent());
    }

    public function testMerchantNotConfirmedReturns409(): void
    {
        $merchant = $this->createUser('merchant-fc-merch-not-confirmed@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-merch-not-confirmed@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $pickupSession = new PickupSession($order);
        $pickupSession->scan(new \DateTimeImmutable('-10 minutes'));
        $order->startPickup();
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Test.'],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_NOT_MERCHANT_CONFIRMED', (string) $response->getContent());
    }

    public function testDelayNotReachedReturns409(): void
    {
        $merchant = $this->createUser('merchant-fc-too-early@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-too-early@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $pickupSession = new PickupSession($order);
        // Scanned only 2 minutes ago — within the 5-minute lock
        $pickupSession->scan(new \DateTimeImmutable('-2 minutes'));
        $order->startPickup();
        $pickupSession->confirmByMerchant(new \DateTimeImmutable('-1 minute'));
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Test.'],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_FORCE_COMPLETION_TOO_EARLY', (string) $response->getContent());
    }

    // --- Validation errors ---

    public function testEmptyNoteReturns422(): void
    {
        $merchant = $this->createUser('merchant-fc-empty-note@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-empty-note@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => ''],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testTooLongNoteReturns422(): void
    {
        $merchant = $this->createUser('merchant-fc-long-note@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-long-note@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => str_repeat('a', 501)],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    // --- TTL / expiry behaviour ---

    /**
     * Expiry guards the scan step only. Once the order is pickup_pending,
     * an expired session TTL must not block force completion.
     */
    public function testExpiredSessionAfterScanDoesNotBlockForceCompletion(): void
    {
        $merchant = $this->createUser('merchant-fc-expired@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-expired@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);
        $this->setPrivateProperty($pickupSession, 'expiresAt', new \DateTimeImmutable('-1 minute'));
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Session expirée mais forçage autorisé.'],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('completed', $payload['order_status']);
        self::assertTrue($payload['force_completed_by_merchant']);
    }

    // --- Idempotency ---

    public function testRepeatedCallDoesNotCreateDoubleCompletedLog(): void
    {
        $merchant = $this->createUser('merchant-fc-repeat@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-fc-repeat@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [$order, $pickupSession] = $this->createForceCompleteReadySession($customer, $shop, $product);

        $firstResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Premier appel.'],
            $merchant,
        );
        self::assertSame(200, $firstResponse->getStatusCode());

        // Second call: session is now used → 409, not a duplicate log
        $secondResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/force-complete', $pickupSession->getId()->toRfc4122()),
            ['note' => 'Deuxième appel.'],
            $merchant,
        );
        self::assertSame(409, $secondResponse->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_ALREADY_USED', (string) $secondResponse->getContent());

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        $logs = $this->entityManager->getRepository(OrderStatusLog::class)->findBy([
            'order' => $updatedOrder,
            'status' => OrderStatus::Completed,
        ]);
        self::assertCount(1, $logs);
    }

    // --- Fixtures ---

    /**
     * Creates a session that is: scanned 10 minutes ago, pickup_pending, merchant confirmed.
     * Ready for force completion (past the 5-minute lock).
     *
     * @return array{0: Order, 1: PickupSession}
     */
    private function createForceCompleteReadySession(User $customer, Shop $shop, MerchantProduct $product): array
    {
        $order = $this->createReadyOrder($customer, $shop, $product);
        $pickupSession = new PickupSession($order);
        $pickupSession->scan(new \DateTimeImmutable('-10 minutes'));
        $order->startPickup();
        $pickupSession->confirmByMerchant(new \DateTimeImmutable('-8 minutes'));

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
            ->setCanonicalName('Brand FC '.$id)
            ->setSlug('brand-fc-'.$id);
        $category = (new Category())
            ->setNameFr('Catégorie FC '.$id)
            ->setSlug('cat-fc-'.$id);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit force-complete')
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
