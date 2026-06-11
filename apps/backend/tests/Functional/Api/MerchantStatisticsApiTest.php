<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\MerchantLocalProduct;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\OrderStatusLog;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;

final class MerchantStatisticsApiTest extends FunctionalApiTestCase
{
    public function testMerchantCanFetchStatistics(): void
    {
        $merchant = $this->createUser('merchant-stats@example.test', ['ROLE_MERCHANT']);
        $store = $this->createShop($merchant);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/statistics', $store->getId()->toRfc4122()),
            user: $merchant
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertArrayHasKey('total_orders', $payload);
        self::assertArrayHasKey('total_revenue_tnd', $payload);
        self::assertArrayHasKey('acceptance_rate', $payload);
        self::assertArrayHasKey('cancellation_rate', $payload);
        self::assertArrayHasKey('rejection_rate', $payload);
        self::assertArrayHasKey('orders_by_status', $payload);
        self::assertArrayHasKey('top_products', $payload);
        self::assertArrayHasKey('top_slots', $payload);
    }

    public function testMerchantOwnershipGuard(): void
    {
        $merchant1 = $this->createUser('merchant-1@example.test', ['ROLE_MERCHANT']);
        $merchant2 = $this->createUser('merchant-2@example.test', ['ROLE_MERCHANT']);

        $store1 = $this->createShop($merchant1);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/statistics', $store1->getId()->toRfc4122()),
            user: $merchant2
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testMerchantStatisticsInvertedPeriodReturns400(): void
    {
        $merchant = $this->createUser('merchant-inverted@example.test', ['ROLE_MERCHANT']);
        $store = $this->createShop($merchant);

        $response = $this->requestJson(
            'GET',
            \sprintf(
                '/api/merchant/stores/%s/statistics?date_from=2026-06-30&date_to=2026-06-01',
                $store->getId()->toRfc4122()
            ),
            user: $merchant
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function testMerchantStatisticsInvalidDateReturns400(): void
    {
        $merchant = $this->createUser('merchant-invalid-date@example.test', ['ROLE_MERCHANT']);
        $store = $this->createShop($merchant);

        $response = $this->requestJson(
            'GET',
            \sprintf(
                '/api/merchant/stores/%s/statistics?date_from=invalid-date',
                $store->getId()->toRfc4122()
            ),
            user: $merchant
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function testMerchantStatisticsUnauthenticatedReturns401(): void
    {
        $merchant = $this->createUser('merchant-auth@example.test', ['ROLE_MERCHANT']);
        $store = $this->createShop($merchant);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/statistics', $store->getId()->toRfc4122())
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testMerchantStatisticsWithEmptyPeriod(): void
    {
        $merchant = $this->createUser('merchant-empty@example.test', ['ROLE_MERCHANT']);
        $store = $this->createShop($merchant);

        $response = $this->requestJson(
            'GET',
            \sprintf(
                '/api/merchant/stores/%s/statistics?date_from=2026-06-01&date_to=2026-06-02',
                $store->getId()->toRfc4122()
            ),
            user: $merchant
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertSame(0, $payload['total_orders']);
        self::assertSame('0.000', $payload['total_revenue_tnd']);
        self::assertSame(0.0, $payload['acceptance_rate']);
    }

    public function testMerchantStatisticsIncludesLocalProductsInTopProducts(): void
    {
        $merchant = $this->createUser('merchant-local-product-stats@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-local-product-stats@example.test', ['ROLE_CUSTOMER']);
        $store = $this->createShop($merchant);
        $product = $this->createLocalMerchantProduct($store, 'Harissa maison', 'هريسة محلية', '3.500');

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($store);
        $order->submit();
        $order->accept();

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(2)
            ->setUnitPriceTnd('3.500')
            ->setLineTotalTnd('7.000');
        $order->addLine($line);
        $order->recomputeTotal();

        $this->entityManager->persist($order);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/statistics', $store->getId()->toRfc4122()),
            user: $merchant
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertSame(1, $payload['total_orders']);
        self::assertSame('7.000', $payload['total_revenue_tnd']);
        self::assertSame([
            [
                'name_fr' => 'Harissa maison',
                'name_ar' => 'هريسة محلية',
                'total_quantity' => 2,
                'total_revenue_tnd' => '7.000',
            ],
        ], $payload['top_products']);
    }

    private function createOrder(User $customer, Shop $shop, OrderStatus $status): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $this->setPrivateProperty($order, 'status', $status);

        $this->entityManager->persist($order);

        return $order;
    }

    private function createLog(Order $order, OrderStatus $status, string $createdAt): OrderStatusLog
    {
        $log = new OrderStatusLog($order, $status);
        $this->setPrivateProperty($log, 'createdAt', new \DateTimeImmutable($createdAt));

        $this->entityManager->persist($log);

        return $log;
    }

    private function createLocalMerchantProduct(Shop $store, string $nameFr, ?string $nameAr, string $priceTnd): MerchantProduct
    {
        $localProduct = (new MerchantLocalProduct())
            ->setShop($store)
            ->setNameFr($nameFr)
            ->setNameAr($nameAr);

        $product = (new MerchantProduct())
            ->setShop($store)
            ->setLocalProduct($localProduct)
            ->setPriceTnd($priceTnd);

        $this->entityManager->persist($localProduct);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }
}
