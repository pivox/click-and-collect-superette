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
use App\Entity\PickupSlot;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\KadhiaStatus;
use App\Enum\ProductReferenceStatus;
use App\Service\PickupSlotDisplayTime;
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
        self::assertSame([], $payload['items']);
        self::assertSame(0, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(20, $payload['limit']);
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
        self::assertSame(2, $payload['total']);
        self::assertCount(2, $payload['items']);
        self::assertSame($order2->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame($order1->getId()->toRfc4122(), $payload['items'][1]['id']);
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
        self::assertSame(1, $payload['total']);
        self::assertCount(1, $payload['items']);
        self::assertSame($orderA->getId()->toRfc4122(), $payload['items'][0]['id']);
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
        $pickupSlot = $order->getPickupSlot();
        self::assertNotNull($pickupSlot);
        self::assertSame($order->getId()->toRfc4122(), $payload['id']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame($shop->getName(), $payload['store_name']);
        self::assertSame('submitted', $payload['status']);
        self::assertSame('5.000', $payload['total_tnd']);
        self::assertSame($pickupSlot->getId()->toRfc4122(), $payload['pickup_slot_id']);
        self::assertSame($pickupSlot->getId()->toRfc4122(), $payload['pickup_slot']['id']);
        self::assertSame(
            PickupSlotDisplayTime::toLocalAtom($pickupSlot->getStartsAt()),
            $payload['pickup_slot']['starts_at'],
        );
        self::assertSame(
            PickupSlotDisplayTime::toLocalAtom($pickupSlot->getEndsAt()),
            $payload['pickup_slot']['ends_at'],
        );
        self::assertNull($payload['notes']);
        self::assertCount(1, $payload['lines']);
        self::assertSame(2, $payload['lines'][0]['quantity']);
        self::assertSame('2.500', $payload['lines'][0]['unit_price_tnd']);
        self::assertSame('5.000', $payload['lines'][0]['line_total_tnd']);
        self::assertArrayHasKey('merchant_product_id', $payload['lines'][0]);
    }

    public function testGetPartiallyAcceptedOrderExposesRejectionReasonAndRejectedLines(): void
    {
        $customer = $this->createUser('history-partial@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $acceptedProduct = $this->createMerchantProduct($shop, '2.500', 'Lait Vitalait 1L');
        $rejectedProduct = $this->createMerchantProduct($shop, '1.800', 'Yaourt nature');
        $kadhia = $this->createSubmittedKadhiaWithLines($customer, $shop, [$acceptedProduct, $rejectedProduct]);
        $slot = $this->createPickupSlot($shop);
        $order = $this->createSubmittedOrderFromKadhia($customer, $shop, $kadhia, $slot);

        $order->partiallyAccept('Rupture de stock.');
        foreach ($kadhia->getLines()->toArray() as $line) {
            if ($line instanceof KadhiaLine && $line->getMerchantProduct()->getId()->equals($rejectedProduct->getId())) {
                $kadhia->removeLine($line);
            }
        }
        $kadhia->setStatus(KadhiaStatus::Draft);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/me/orders/%s', $order->getId()->toRfc4122()), user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertSame('partially_accepted', $payload['status']);
        self::assertSame('Rupture de stock.', $payload['rejection_reason']);
        self::assertCount(2, $payload['lines']);
        self::assertCount(1, $payload['rejected_lines']);
        self::assertSame($rejectedProduct->getId()->toRfc4122(), $payload['rejected_lines'][0]['merchant_product_id']);
        self::assertSame('Yaourt nature', $payload['rejected_lines'][0]['product_name']);
        self::assertSame(1, $payload['rejected_lines'][0]['quantity']);
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

        $product = $this->createMerchantProductFromReference($shop, $ref, '2.500');

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
            $line = (new OrderLine())
                ->setMerchantProduct($kadhiaLine->getMerchantProduct())
                ->setQuantity($kadhiaLine->getQuantity())
                ->setUnitPriceTnd($kadhiaLine->getUnitPriceTnd())
                ->setLineTotalTnd(bcmul((string) $kadhiaLine->getQuantity(), $kadhiaLine->getUnitPriceTnd(), 3));
            $order->addLine($line);
            $this->entityManager->persist($line);
        }

        $order->recomputeTotal();
        $this->entityManager->flush();

        return $order;
    }

    private function createMerchantProduct(Shop $shop, string $priceTnd, string $nameFr): MerchantProduct
    {
        $uniqueId = Uuid::v4();

        $brand = (new Brand())
            ->setCanonicalName('Marque '.$nameFr)
            ->setSlug('marque-history-'.$uniqueId);
        $this->entityManager->persist($brand);

        $category = (new Category())
            ->setNameFr('Catégorie '.$nameFr)
            ->setSlug('categorie-history-'.$uniqueId);
        $this->entityManager->persist($category);

        $ref = (new ProductReference())
            ->setNameFr($nameFr)
            ->setBrand($brand)
            ->setCategory($category)
            ->setStatus(ProductReferenceStatus::Approved);
        $this->entityManager->persist($ref);

        return $this->createMerchantProductFromReference($shop, $ref, $priceTnd);
    }

    private function createMerchantProductFromReference(Shop $shop, ProductReference $ref, string $priceTnd): MerchantProduct
    {
        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd($priceTnd)
            ->setAvailable(true)
            ->setVisible(true);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }
}
