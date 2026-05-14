<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Kadhia;
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

final class OrderCancelApiTest extends FunctionalApiTestCase
{
    public function testCustomerCanCancelOwnSubmittedOrder(): void
    {
        $customer = $this->createUser('cancel-owner@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop);
        $slot->book();
        $kadhia = $this->createSubmittedKadhia($customer, $shop);
        $order = $this->createOrder($customer, $shop, OrderStatus::Submitted, $slot, $kadhia);
        $bookedBefore = $slot->getBookedCount();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/orders/%s/cancel', $order->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($order->getId()->toRfc4122(), $payload['id']);
        self::assertSame('cancelled', $payload['status']);
        self::assertSame($slot->getId()->toRfc4122(), $payload['pickup_slot_id']);
        self::assertCount(1, $payload['lines']);

        $logs = $this->findStatusLogs($order);
        self::assertCount(1, $logs);
        self::assertSame(OrderStatus::Cancelled, $logs[0]->getStatus());
        self::assertNull($logs[0]->getNote());

        $this->entityManager->refresh($slot);
        $this->entityManager->refresh($kadhia);
        self::assertSame($bookedBefore - 1, $slot->getBookedCount());
        self::assertSame(KadhiaStatus::Submitted, $kadhia->getStatus());

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updated);
        self::assertSame(OrderStatus::Cancelled, $updated->getStatus());
        self::assertCount(1, $updated->getLines());
    }

    public function testCancelledStatusLogIsVisibleInCustomerStatusHistory(): void
    {
        $customer = $this->createUser('cancel-history@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createOrder($customer, $shop, OrderStatus::Submitted);

        $cancelResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/orders/%s/cancel', $order->getId()->toRfc4122()),
            [],
            $customer,
        );
        self::assertSame(200, $cancelResponse->getStatusCode());

        $historyResponse = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status-history', $order->getId()->toRfc4122()),
            null,
            $customer,
        );

        self::assertSame(200, $historyResponse->getStatusCode());
        $payload = $this->decodeJson($historyResponse);
        self::assertSame($order->getId()->toRfc4122(), $payload['order_id']);
        self::assertCount(1, $payload['transitions']);
        self::assertSame('cancelled', $payload['transitions'][0]['status']);
        self::assertNull($payload['transitions'][0]['note']);
    }

    public function testCancelledOrderRemainsVisibleInCustomerOrderDetailAndHistory(): void
    {
        $customer = $this->createUser('cancel-visible@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createOrder($customer, $shop, OrderStatus::Submitted);

        $cancelResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/orders/%s/cancel', $order->getId()->toRfc4122()),
            [],
            $customer,
        );
        self::assertSame(200, $cancelResponse->getStatusCode());

        $detailResponse = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s', $order->getId()->toRfc4122()),
            null,
            $customer,
        );
        self::assertSame(200, $detailResponse->getStatusCode());
        self::assertSame('cancelled', $this->decodeJson($detailResponse)['status']);

        $collectionResponse = $this->requestJson('GET', '/api/me/orders', null, $customer);
        self::assertSame(200, $collectionResponse->getStatusCode());
        $payload = $this->decodeJson($collectionResponse);
        self::assertSame(1, $payload['total']);
        self::assertSame($order->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('cancelled', $payload['items'][0]['status']);
    }

    public function testCustomerCannotCancelAnotherCustomerOrder(): void
    {
        $owner = $this->createUser('cancel-owner-denied@example.test', ['ROLE_CUSTOMER']);
        $other = $this->createUser('cancel-other-denied@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createOrder($owner, $shop, OrderStatus::Submitted);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/orders/%s/cancel', $order->getId()->toRfc4122()),
            [],
            $other,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_FOUND', (string) $response->getContent());
        self::assertSame([], $this->findStatusLogs($order));
    }

    public function testMerchantCannotCallCustomerCancelEndpoint(): void
    {
        $merchant = $this->createUser('cancel-merchant@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/orders/%s/cancel', Uuid::v4()->toRfc4122()),
            [],
            $merchant,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testUnauthenticatedCancelReturns401(): void
    {
        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/orders/%s/cancel', Uuid::v4()->toRfc4122()),
            [],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    #[DataProvider('nonSubmittedStatusProvider')]
    public function testCustomerCannotCancelNonSubmittedOrder(OrderStatus $status): void
    {
        $customer = $this->createUser('cancel-invalid-'.$status->value.'@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop);
        $slot->book();
        $order = $this->createOrder($customer, $shop, $status, $slot);
        $bookedBefore = $slot->getBookedCount();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/orders/%s/cancel', $order->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_SUBMITTED', (string) $response->getContent());

        $this->entityManager->refresh($slot);
        $this->entityManager->refresh($order);
        self::assertSame($status, $order->getStatus());
        self::assertSame($bookedBefore, $slot->getBookedCount());
        self::assertSame([], $this->findStatusLogs($order));
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

    private function createSubmittedKadhia(User $customer, Shop $shop): Kadhia
    {
        $kadhia = (new Kadhia())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setStatus(KadhiaStatus::Submitted);

        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        return $kadhia;
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

    private function createOrder(
        User $customer,
        Shop $shop,
        OrderStatus $status,
        ?PickupSlot $slot = null,
        ?Kadhia $kadhia = null,
    ): Order {
        $product = $this->createMerchantProduct($shop);

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setKadhia($kadhia)
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
        match ($status) {
            OrderStatus::Draft => null,
            OrderStatus::Submitted => $order->submit(),
            OrderStatus::Accepted => $this->moveToAccepted($order),
            OrderStatus::PartiallyAccepted => $this->moveToPartiallyAccepted($order),
            OrderStatus::Rejected => $this->moveToRejected($order),
            OrderStatus::Preparing => $this->moveToPreparing($order),
            OrderStatus::Ready => $this->moveToReady($order),
            OrderStatus::PickupPending => $this->moveToPickupPending($order),
            OrderStatus::Completed => $this->moveToCompleted($order),
            OrderStatus::Cancelled => $this->moveToCancelled($order),
            default => throw new \InvalidArgumentException('Unsupported order status for cancel test.'),
        };

        $this->entityManager->flush();

        return $order;
    }

    private function moveToAccepted(Order $order): void
    {
        $order->submit();
        $order->accept();
    }

    private function moveToCancelled(Order $order): void
    {
        $order->submit();
        $order->cancel();
    }

    private function moveToPartiallyAccepted(Order $order): void
    {
        $order->submit();
        $order->partiallyAccept();
    }

    private function moveToRejected(Order $order): void
    {
        $order->submit();
        $order->reject('Rupture de stock');
    }

    private function moveToPreparing(Order $order): void
    {
        $this->moveToAccepted($order);
        $order->startPreparing();
    }

    private function moveToReady(Order $order): void
    {
        $this->moveToPreparing($order);
        foreach ($order->getLines() as $line) {
            $line->markPrepared(true);
        }
        $order->markReady();
    }

    private function moveToCompleted(Order $order): void
    {
        $this->moveToPickupPending($order);
        $order->complete();
    }

    private function moveToPickupPending(Order $order): void
    {
        $this->moveToReady($order);
        $order->startPickup();
    }

    private function createMerchantProduct(Shop $shop): MerchantProduct
    {
        $uniqueId = Uuid::v4();

        $brand = (new Brand())
            ->setCanonicalName('Marque Test')
            ->setSlug('marque-test-'.$uniqueId);
        $this->entityManager->persist($brand);

        $category = (new Category())
            ->setNameFr('Categorie Test')
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

        return $product;
    }

    /**
     * @return list<OrderStatusLog>
     */
    private function findStatusLogs(Order $order): array
    {
        return $this->entityManager->getRepository(OrderStatusLog::class)->findBy(['order' => $order], ['createdAt' => 'ASC']);
    }
}
