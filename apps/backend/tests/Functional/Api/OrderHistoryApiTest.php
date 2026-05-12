<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\PickupSlot;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use Symfony\Component\Uid\Uuid;

final class OrderHistoryApiTest extends FunctionalApiTestCase
{
    // GET /api/me/orders

    public function testGetOrdersEmpty(): void
    {
        $customer = $this->createUser('history-empty@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('GET', '/api/me/orders', user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame([], $payload);
    }

    public function testGetOrdersReturnsList(): void
    {
        $customer = $this->createUser('history-list@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $order1 = $this->createSubmittedOrder($customer, $shop);
        (new \ReflectionProperty($order1, 'createdAt'))->setValue($order1, new \DateTimeImmutable('-2 hours'));
        $this->entityManager->flush();

        $order2 = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson('GET', '/api/me/orders', user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(2, $payload);
        self::assertSame($order2->getId()->toRfc4122(), $payload[0]['id']);
        self::assertSame($order1->getId()->toRfc4122(), $payload[1]['id']);
    }

    public function testGetOrdersOnlyReturnsOwnOrders(): void
    {
        $customerA = $this->createUser('history-own-a@example.test', ['ROLE_CUSTOMER']);
        $customerB = $this->createUser('history-own-b@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $orderA = $this->createSubmittedOrder($customerA, $shop);
        $this->createSubmittedOrder($customerB, $shop);

        $response = $this->requestJson('GET', '/api/me/orders', user: $customerA);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload);
        self::assertSame($orderA->getId()->toRfc4122(), $payload[0]['id']);
    }

    public function testGetOrdersUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson('GET', '/api/me/orders');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testGetOrdersMerchantRoleReturns403(): void
    {
        $merchant = $this->createUser('history-merchant@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('GET', '/api/me/orders', user: $merchant);

        self::assertSame(403, $response->getStatusCode());
    }

    // GET /api/me/orders/{id}

    public function testGetOrderByIdHappyPath(): void
    {
        $customer = $this->createUser('history-item@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson('GET', \sprintf('/api/me/orders/%s', $order->getId()->toRfc4122()), user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($order->getId()->toRfc4122(), $payload['id']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame('submitted', $payload['status']);
        self::assertSame('5.000', $payload['total_tnd']);
        self::assertNotNull($payload['pickup_slot_id']);
        self::assertNull($payload['notes']);
        self::assertCount(1, $payload['lines']);
        self::assertSame(2, $payload['lines'][0]['quantity']);
        self::assertSame('2.500', $payload['lines'][0]['unit_price_tnd']);
        self::assertSame('5.000', $payload['lines'][0]['line_total_tnd']);
        self::assertArrayHasKey('merchant_product_id', $payload['lines'][0]);
    }

    public function testGetOrderByIdNotFoundReturns404(): void
    {
        $customer = $this->createUser('history-notfound@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'GET',
            '/api/me/orders/00000000-0000-0000-0000-000000000099',
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_FOUND', (string) $response->getContent());
    }

    public function testGetOrderByIdBelongingToAnotherCustomerReturns404(): void
    {
        $customerA = $this->createUser('history-cross-a@example.test', ['ROLE_CUSTOMER']);
        $customerB = $this->createUser('history-cross-b@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $orderB = $this->createSubmittedOrder($customerB, $shop);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s', $orderB->getId()->toRfc4122()),
            user: $customerA,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_FOUND', (string) $response->getContent());
    }

    public function testGetOrderByIdUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson('GET', '/api/me/orders/00000000-0000-0000-0000-000000000001');

        self::assertSame(401, $response->getStatusCode());
    }

    // Helpers

    private function createSubmittedOrder(User $customer, Shop $shop): Order
    {
        $uniqueId = Uuid::v4();

        $brand = (new Brand())
            ->setCanonicalName('Marque Test')
            ->setSlug('marque-test-'.$uniqueId);
        $this->entityManager->persist($brand);

        $category = (new Category())
            ->setNameFr('Catégorie Test')
            ->setSlug('categorie-test-'.$uniqueId);
        $this->entityManager->persist($category);

        $ref = (new ProductReference())
            ->setNameFr('Produit Test '.$uniqueId)
            ->setBrand($brand)
            ->setCategory($category)
            ->setStatus(ProductReferenceStatus::Approved);
        $this->entityManager->persist($ref);

        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd('2.500')
            ->setAvailable(true)
            ->setVisible(true);
        $this->entityManager->persist($product);

        $now = new \DateTimeImmutable();
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(5);
        $this->entityManager->persist($slot);

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setPickupSlot($slot);
        $this->entityManager->persist($order);

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(2)
            ->setUnitPriceTnd('2.500')
            ->setLineTotalTnd('5.000');
        $order->addLine($line);
        $this->entityManager->persist($line);

        $order->recomputeTotal();
        $order->submit();

        $this->entityManager->flush();

        return $order;
    }
}
