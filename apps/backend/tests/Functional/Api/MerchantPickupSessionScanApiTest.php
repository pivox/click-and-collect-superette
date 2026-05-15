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

final class MerchantPickupSessionScanApiTest extends FunctionalApiTestCase
{
    public function testMerchantOwnerScansValidPickupSession(): void
    {
        $merchant = $this->createUser('merchant-scan@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('customer-scan@example.test');
        $product = $this->createMerchantProduct($shop, '2.800', 'Lait Vitalait 1L');
        $order = $this->createReadyOrder($customer, $shop, $product);
        $pickupSession = new PickupSession($order);
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            '/api/merchant/pickup-sessions/scan',
            ['token' => $pickupSession->getToken()->toRfc4122()],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame($pickupSession->getId()->toRfc4122(), $payload['id']);
        self::assertSame($order->getId()->toRfc4122(), $payload['order_id']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertArrayHasKey('order_number', $payload);
        self::assertNull($payload['order_number']);
        self::assertSame('pickup_pending', $payload['status']);
        self::assertNotEmpty($payload['scanned_at']);
        self::assertSame('Haythem', $payload['customer']['first_name']);
        self::assertSame('Mabrouk', $payload['customer']['last_name']);
        self::assertSame('+21600000000', $payload['customer']['phone']);
        self::assertCount(1, $payload['lines']);
        self::assertSame($product->getId()->toRfc4122(), $payload['lines'][0]['merchant_product_id']);
        self::assertSame('Lait Vitalait 1L', $payload['lines'][0]['name']);
        self::assertSame(2, $payload['lines'][0]['quantity']);
        self::assertSame('2.800', $payload['lines'][0]['unit_price_tnd']);
        self::assertArrayNotHasKey('customer_email', $payload);
        self::assertArrayNotHasKey('password', $payload['customer']);

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::PickupPending, $updatedOrder->getStatus());

        $updatedSession = $this->entityManager->getRepository(PickupSession::class)->find($pickupSession->getId());
        self::assertNotNull($updatedSession);
        self::assertNotNull($updatedSession->getScannedAt());

        $logs = $this->entityManager->getRepository(OrderStatusLog::class)->findBy(['order' => $updatedOrder]);
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::PickupPending, $logs[0]->getStatus());
    }

    public function testScanIsIdempotentWhenAlreadyScannedAndPickupPending(): void
    {
        $merchant = $this->createUser('merchant-scan-idempotent@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('customer-scan-idempotent@example.test');
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $pickupSession = new PickupSession($order);
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $firstResponse = $this->requestJson(
            'POST',
            '/api/merchant/pickup-sessions/scan',
            ['token' => $pickupSession->getToken()->toRfc4122()],
            $merchant,
        );
        self::assertSame(200, $firstResponse->getStatusCode());
        $firstPayload = $this->decodeJson($firstResponse);

        $secondResponse = $this->requestJson(
            'POST',
            '/api/merchant/pickup-sessions/scan',
            ['token' => $pickupSession->getToken()->toRfc4122()],
            $merchant,
        );

        self::assertSame(200, $secondResponse->getStatusCode());
        $secondPayload = $this->decodeJson($secondResponse);
        self::assertSame('pickup_pending', $secondPayload['status']);
        self::assertSame($firstPayload['scanned_at'], $secondPayload['scanned_at']);

        $logs = $this->entityManager->getRepository(OrderStatusLog::class)->findBy(['order' => $order]);
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::PickupPending, $logs[0]->getStatus());
    }

    public function testScanUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson(
            'POST',
            '/api/merchant/pickup-sessions/scan',
            ['token' => Uuid::v4()->toRfc4122()],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testScanCustomerRoleReturns403(): void
    {
        $customer = $this->createUser('customer-scan-role@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'POST',
            '/api/merchant/pickup-sessions/scan',
            ['token' => Uuid::v4()->toRfc4122()],
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testScanWrongMerchantReturns403(): void
    {
        $merchantA = $this->createUser('merchant-scan-a@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-scan-b@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchantB);
        $customer = $this->createCustomer('customer-scan-forbidden@example.test');
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $pickupSession = new PickupSession($order);
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            '/api/merchant/pickup-sessions/scan',
            ['token' => $pickupSession->getToken()->toRfc4122()],
            $merchantA,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testScanUnknownTokenReturns404(): void
    {
        $merchant = $this->createUser('merchant-scan-unknown@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson(
            'POST',
            '/api/merchant/pickup-sessions/scan',
            ['token' => Uuid::v4()->toRfc4122()],
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_NOT_FOUND', (string) $response->getContent());
    }

    public function testScanInvalidTokenFormatReturns422(): void
    {
        $merchant = $this->createUser('merchant-scan-invalid-token@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson(
            'POST',
            '/api/merchant/pickup-sessions/scan',
            ['token' => 'not-a-uuid'],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testScanExpiredSessionReturns409(): void
    {
        $merchant = $this->createUser('merchant-scan-expired@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('customer-scan-expired@example.test');
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $pickupSession = new PickupSession($order);
        $this->setPrivateProperty($pickupSession, 'expiresAt', new \DateTimeImmutable('-1 minute'));
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            '/api/merchant/pickup-sessions/scan',
            ['token' => $pickupSession->getToken()->toRfc4122()],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_EXPIRED', (string) $response->getContent());
    }

    public function testScanNonReadyOrderReturns409(): void
    {
        $merchant = $this->createUser('merchant-scan-non-ready@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('customer-scan-non-ready@example.test');
        $order = $this->createAcceptedOrder($customer, $shop);
        $pickupSession = new PickupSession($order);
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            '/api/merchant/pickup-sessions/scan',
            ['token' => $pickupSession->getToken()->toRfc4122()],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_READY', (string) $response->getContent());
    }

    public function testScanUsedSessionReturns409(): void
    {
        $merchant = $this->createUser('merchant-scan-used@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('customer-scan-used@example.test');
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $pickupSession = new PickupSession($order);
        $this->setPrivateProperty($pickupSession, 'used', true);
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            '/api/merchant/pickup-sessions/scan',
            ['token' => $pickupSession->getToken()->toRfc4122()],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_ALREADY_USED', (string) $response->getContent());
    }

    public function testScanCompletedOrderReturns409(): void
    {
        $merchant = $this->createUser('merchant-scan-completed@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('customer-scan-completed@example.test');
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $order->startPickup();
        $order->complete();
        $pickupSession = new PickupSession($order);
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            '/api/merchant/pickup-sessions/scan',
            ['token' => $pickupSession->getToken()->toRfc4122()],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_ALREADY_USED', (string) $response->getContent());
    }

    private function createCustomer(string $email): User
    {
        $customer = $this->createUser($email, ['ROLE_CUSTOMER']);
        $customer
            ->setFirstName('Haythem')
            ->setLastName('Mabrouk')
            ->setName('Haythem Mabrouk')
            ->setPhone('+21600000000');
        $this->entityManager->flush();

        return $customer;
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

    private function createMerchantProduct(
        Shop $shop,
        string $priceTnd = '1.000',
        string $nameFr = 'Produit retrait',
    ): MerchantProduct {
        $id = Uuid::v4()->toRfc4122();
        $brand = (new Brand())
            ->setCanonicalName('Brand Scan '.$id)
            ->setSlug('brand-scan-'.$id);
        $category = (new Category())
            ->setNameFr('Catégorie Scan '.$id)
            ->setSlug('categorie-scan-'.$id);
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
}
