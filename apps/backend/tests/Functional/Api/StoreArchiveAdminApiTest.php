<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\OrderStatusLog;
use App\Entity\PickupSlot;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Symfony\Component\Uid\Uuid;

final class StoreArchiveAdminApiTest extends FunctionalApiTestCase
{
    public function testAdminArchivesActiveStore(): void
    {
        $admin = $this->createUser('admin-archive@example.test', ['ROLE_ADMIN']);
        $shop = $this->createShop(active: true);
        $originalToken = $shop->getQrCodeToken();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            ['reason' => 'Fermeture définitive du commerce'],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['id']);
        self::assertFalse($payload['is_active']);
        self::assertArrayHasKey('archived_at', $payload);
        self::assertNotNull($payload['archived_at']);
        self::assertSame('Fermeture définitive du commerce', $payload['archive_reason']);
        self::assertSame($originalToken, $payload['qr_code_token']);

        $this->entityManager->refresh($shop);
        self::assertNotNull($shop->getArchivedAt());
        self::assertSame('Fermeture définitive du commerce', $shop->getArchiveReason());
        self::assertFalse($shop->isActive());
    }

    public function testAdminArchivesStoreWithoutReason(): void
    {
        $admin = $this->createUser('admin-archive-noreason@example.test', ['ROLE_ADMIN']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertFalse($payload['is_active']);
        self::assertNotNull($payload['archived_at']);
    }

    public function testArchivedStoreNotAccessibleByQrCode(): void
    {
        $admin = $this->createUser('admin-archive-qr@example.test', ['ROLE_ADMIN']);
        $shop = $this->createShop();
        $token = $shop->getQrCodeToken();

        $qrBefore = $this->requestJson('GET', \sprintf('/api/stores/by-qr/%s', $token));
        self::assertSame(200, $qrBefore->getStatusCode());

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $qrAfter = $this->requestJson('GET', \sprintf('/api/stores/by-qr/%s', $token));
        self::assertSame(404, $qrAfter->getStatusCode());
    }

    public function testArchiveCancelsSubmittedOrders(): void
    {
        $admin = $this->createUser('admin-archive-submitted@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-archive-submitted@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $order = $this->createOrder($customer, $shop, OrderStatus::Submitted);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $this->entityManager->refresh($order);
        self::assertSame(OrderStatus::Cancelled, $order->getStatus());
    }

    public function testArchiveCancelsAcceptedOrders(): void
    {
        $admin = $this->createUser('admin-archive-accepted@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-archive-accepted@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $order = $this->createOrder($customer, $shop, OrderStatus::Accepted);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $this->entityManager->refresh($order);
        self::assertSame(OrderStatus::Cancelled, $order->getStatus());
    }

    public function testArchiveCancelsPartiallyAcceptedOrders(): void
    {
        $admin = $this->createUser('admin-archive-partial@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-archive-partial@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $order = $this->createOrder($customer, $shop, OrderStatus::PartiallyAccepted);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $this->entityManager->refresh($order);
        self::assertSame(OrderStatus::Cancelled, $order->getStatus());
    }

    public function testArchiveCancelsPreparingOrders(): void
    {
        $admin = $this->createUser('admin-archive-preparing@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-archive-preparing@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $order = $this->createOrder($customer, $shop, OrderStatus::Preparing);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $this->entityManager->refresh($order);
        self::assertSame(OrderStatus::Cancelled, $order->getStatus());
    }

    public function testArchiveCancelsReadyOrders(): void
    {
        $admin = $this->createUser('admin-archive-ready@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-archive-ready@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $order = $this->createOrder($customer, $shop, OrderStatus::Ready);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $this->entityManager->refresh($order);
        self::assertSame(OrderStatus::Cancelled, $order->getStatus());
    }

    public function testArchiveCancelsPickupPendingOrders(): void
    {
        $admin = $this->createUser('admin-archive-pickup@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-archive-pickup@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $order = $this->createOrder($customer, $shop, OrderStatus::PickupPending);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $this->entityManager->refresh($order);
        self::assertSame(OrderStatus::Cancelled, $order->getStatus());
    }

    public function testArchiveDoesNotCancelCompletedOrders(): void
    {
        $admin = $this->createUser('admin-archive-completed@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-archive-completed@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $order = $this->createOrder($customer, $shop, OrderStatus::Completed);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $this->entityManager->refresh($order);
        self::assertSame(OrderStatus::Completed, $order->getStatus());
    }

    public function testArchiveDoesNotCancelAlreadyCancelledOrders(): void
    {
        $admin = $this->createUser('admin-archive-cancelled@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-archive-cancelled@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $order = $this->createOrder($customer, $shop, OrderStatus::Cancelled);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $this->entityManager->refresh($order);
        self::assertSame(OrderStatus::Cancelled, $order->getStatus());
    }

    public function testArchiveDoesNotCancelRejectedOrders(): void
    {
        $admin = $this->createUser('admin-archive-rejected@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-archive-rejected@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $order = $this->createOrder($customer, $shop, OrderStatus::Rejected);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $this->entityManager->refresh($order);
        self::assertSame(OrderStatus::Rejected, $order->getStatus());
    }

    public function testArchiveDoesNotCancelDraftOrders(): void
    {
        $admin = $this->createUser('admin-archive-draft@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-archive-draft@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $order = $this->createOrder($customer, $shop, OrderStatus::Draft);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $this->entityManager->refresh($order);
        self::assertSame(OrderStatus::Draft, $order->getStatus());
    }

    public function testArchiveDecrementsPickupSlotBookedCount(): void
    {
        $admin = $this->createUser('admin-archive-slot@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-archive-slot@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $slot = $this->createPickupSlot($shop);
        $slot->book();
        $this->entityManager->flush();
        self::assertSame(1, $slot->getBookedCount());

        $this->createOrder($customer, $shop, OrderStatus::Submitted, $slot);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $this->entityManager->refresh($slot);
        self::assertSame(0, $slot->getBookedCount());
    }

    public function testHistoryPreservedAfterArchive(): void
    {
        $admin = $this->createUser('admin-archive-history@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-archive-history@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $completedOrder = $this->createOrder($customer, $shop, OrderStatus::Completed);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $fetched = $this->entityManager->find(Order::class, $completedOrder->getId());
        self::assertNotNull($fetched);
        self::assertSame(OrderStatus::Completed, $fetched->getStatus());
    }

    public function testArchiveCancelledOrderHasStatusLogEntry(): void
    {
        $admin = $this->createUser('admin-archive-log@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-archive-log@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createOrder($customer, $shop, OrderStatus::Submitted);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        $this->entityManager->refresh($order);
        $logs = $this->entityManager->getRepository(OrderStatusLog::class)->findBy(['order' => $order], ['createdAt' => 'ASC']);
        self::assertNotEmpty($logs);
        $lastLog = end($logs);
        self::assertSame(OrderStatus::Cancelled, $lastLog->getStatus());
        self::assertSame('ADMIN_STORE_ARCHIVED', $lastLog->getNote());
    }

    public function testArchiveWithEmptyReasonReturns422(): void
    {
        $admin = $this->createUser('admin-archive-empty-reason@example.test', ['ROLE_ADMIN']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            ['reason' => ''],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testArchiveAlreadyArchivedStoreReturns409(): void
    {
        $admin = $this->createUser('admin-archive-twice@example.test', ['ROLE_ADMIN']);
        $shop = $this->createShop();

        $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/archive', $shop->getId()), [], $admin);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            [],
            $admin,
        );

        self::assertSame(409, $response->getStatusCode());
    }

    public function testArchiveMissingStoreReturns404(): void
    {
        $admin = $this->createUser('admin-archive-missing@example.test', ['ROLE_ADMIN']);
        $unknownId = Uuid::v4()->toRfc4122();

        $response = $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/archive', $unknownId), [], $admin);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testArchiveForbiddenForNonAdmin(): void
    {
        $merchant = $this->createUser('merchant-archive-forbidden@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-archive-forbidden@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/archive', $shop->getId()), [], $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/archive', $shop->getId()), [], $customer)->getStatusCode());
        self::assertSame(401, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/archive', $shop->getId()), [])->getStatusCode());
    }

    public function testAdminStoreListShowsArchivedAt(): void
    {
        $admin = $this->createUser('admin-archive-list@example.test', ['ROLE_ADMIN']);
        $shop = $this->createShop();

        $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/archive', $shop->getId()), [], $admin);

        $response = $this->requestJson('GET', '/api/admin/stores', user: $admin);
        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertArrayHasKey('archived_at', $payload['items'][0]);
        self::assertNotNull($payload['items'][0]['archived_at']);
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
    ): Order {
        $product = $this->createMerchantProduct($shop);

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setPickupSlot($slot);

        $this->entityManager->persist($order);

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd('2.500')
            ->setLineTotalTnd('2.500');
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
            default => throw new \InvalidArgumentException('Unsupported status: '.$status->value),
        };

        $this->entityManager->flush();

        return $order;
    }

    private function moveToAccepted(Order $order): void
    {
        $order->submit();
        $order->accept();
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

    private function moveToPickupPending(Order $order): void
    {
        $this->moveToReady($order);
        $order->startPickup();
    }

    private function moveToCompleted(Order $order): void
    {
        $this->moveToPickupPending($order);
        $order->complete();
    }

    private function moveToCancelled(Order $order): void
    {
        $order->submit();
        $order->cancel();
    }

    private function createMerchantProduct(Shop $shop): MerchantProduct
    {
        $uniqueId = Uuid::v4()->toRfc4122();
        $brand = (new Brand())
            ->setCanonicalName('Marque '.$uniqueId)
            ->setSlug('brand-'.$uniqueId)
            ->setActive(true);
        $this->entityManager->persist($brand);

        $category = (new Category())
            ->setNameFr('Catégorie '.$uniqueId)
            ->setSlug('cat-'.$uniqueId)
            ->setActive(true);
        $this->entityManager->persist($category);

        $productRef = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit '.$uniqueId)
            ->setUnit(ProductUnit::Piece)
            ->setStatus(ProductReferenceStatus::Approved);
        $this->entityManager->persist($productRef);

        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productRef)
            ->setPriceTnd('2.500')
            ->setAvailable(true)
            ->setVisible(true);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }
}
