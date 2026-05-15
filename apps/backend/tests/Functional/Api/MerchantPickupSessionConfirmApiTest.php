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
use App\Enum\OrderStatus;
use App\Enum\ProductReferenceStatus;
use Symfony\Component\Uid\Uuid;

final class MerchantPickupSessionConfirmApiTest extends FunctionalApiTestCase
{
    public function testMerchantOwnerConfirmsScannedPickupSession(): void
    {
        $merchant = $this->createUser('merchant-confirm@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-confirm@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [$order, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame($pickupSession->getId()->toRfc4122(), $payload['id']);
        self::assertSame($order->getId()->toRfc4122(), $payload['order_id']);
        self::assertSame('pickup_pending', $payload['order_status']);
        self::assertNotEmpty($payload['scanned_at']);
        self::assertNotEmpty($payload['merchant_confirmed_at']);
        self::assertNull($payload['customer_confirmed_at']);
        self::assertFalse($payload['is_used']);
        self::assertFalse($payload['is_completed']);
        self::assertArrayNotHasKey('token', $payload);
        self::assertArrayNotHasKey('customer', $payload);
        self::assertArrayNotHasKey('lines', $payload);

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::PickupPending, $updatedOrder->getStatus());

        $updatedSession = $this->entityManager->getRepository(PickupSession::class)->find($pickupSession->getId());
        self::assertNotNull($updatedSession);
        self::assertNotNull($updatedSession->getMerchantConfirmedAt());
        self::assertFalse($updatedSession->isUsed());
    }

    public function testMerchantConfirmationIsIdempotentWhilePickupPending(): void
    {
        $merchant = $this->createUser('merchant-confirm-idempotent@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-confirm-idempotent@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);

        $firstResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $merchant,
        );
        self::assertSame(200, $firstResponse->getStatusCode());
        $firstPayload = $this->decodeJson($firstResponse);

        $secondResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $merchant,
        );

        self::assertSame(200, $secondResponse->getStatusCode());
        $secondPayload = $this->decodeJson($secondResponse);
        self::assertSame($firstPayload['merchant_confirmed_at'], $secondPayload['merchant_confirmed_at']);
        self::assertSame('pickup_pending', $secondPayload['order_status']);
        self::assertFalse($secondPayload['is_used']);
    }

    public function testMerchantConfirmationUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/confirm', Uuid::v4()->toRfc4122()),
            [],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testMerchantConfirmationCustomerRoleReturns403(): void
    {
        $customer = $this->createUser('customer-confirm-role@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/confirm', Uuid::v4()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testMerchantConfirmationWrongMerchantReturns403(): void
    {
        $merchantA = $this->createUser('merchant-confirm-a@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-confirm-b@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchantB);
        $customer = $this->createUser('customer-confirm-forbidden@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $merchantA,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testMerchantConfirmationUnknownSessionReturns404(): void
    {
        $merchant = $this->createUser('merchant-confirm-unknown@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/confirm', Uuid::v4()->toRfc4122()),
            [],
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_NOT_FOUND', (string) $response->getContent());
    }

    public function testMerchantConfirmationNotScannedSessionReturns409(): void
    {
        $merchant = $this->createUser('merchant-confirm-not-scanned@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-confirm-not-scanned@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $order->startPickup();
        $pickupSession = new PickupSession($order);
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_NOT_SCANNED', (string) $response->getContent());
    }

    public function testMerchantConfirmationExpiredSessionReturns409(): void
    {
        $merchant = $this->createUser('merchant-confirm-expired@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-confirm-expired@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);
        $this->setPrivateProperty($pickupSession, 'expiresAt', new \DateTimeImmutable('-1 minute'));
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_EXPIRED', (string) $response->getContent());
    }

    public function testMerchantConfirmationNonPickupPendingOrderReturns409(): void
    {
        $merchant = $this->createUser('merchant-confirm-non-pending@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-confirm-non-pending@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createReadyOrder($customer, $shop, $product);
        $pickupSession = new PickupSession($order);
        $pickupSession->scan();
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_PICKUP_PENDING', (string) $response->getContent());
    }

    public function testMerchantConfirmationUsedSessionReturns409(): void
    {
        $merchant = $this->createUser('merchant-confirm-used@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-confirm-used@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);
        $this->setPrivateProperty($pickupSession, 'used', true);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_ALREADY_USED', (string) $response->getContent());
    }

    public function testMerchantConfirmationCompletedOrderReturns409(): void
    {
        $merchant = $this->createUser('merchant-confirm-completed@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-confirm-completed@example.test', ['ROLE_CUSTOMER']);
        $product = $this->createMerchantProduct($shop);
        [$order, $pickupSession] = $this->createScannedPickupPendingSession($customer, $shop, $product);
        $order->complete();
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/pickup-sessions/%s/confirm', $pickupSession->getId()->toRfc4122()),
            [],
            $merchant,
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
        $pickupSession->scan(new \DateTimeImmutable('2026-05-15 14:00:00'));
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
            ->setCanonicalName('Brand Confirm '.$id)
            ->setSlug('brand-confirm-'.$id);
        $category = (new Category())
            ->setNameFr('Catégorie Confirm '.$id)
            ->setSlug('categorie-confirm-'.$id);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit retrait')
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
