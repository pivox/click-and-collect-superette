<?php

declare(strict_types=1);

namespace App\Tests\Functional\Doctrine;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\PickupSession;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\ProductReferenceStatus;
use App\Repository\NotificationRepository;
use App\Service\OrderTransitionService;
use App\Tests\Functional\Api\FunctionalApiTestCase;

final class Sprint4FoundationDoctrineTest extends FunctionalApiTestCase
{
    public function testPickupSessionCanBePersistedAndScanned(): void
    {
        $customer = $this->createUser('customer-pickup-session@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);

        $generatedAt = new \DateTimeImmutable('2026-05-15 10:00:00');
        $pickupSession = new PickupSession($order, $generatedAt);

        $this->entityManager->persist($order);
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        self::assertSame($generatedAt, $pickupSession->getGeneratedAt());
        self::assertSame('2026-05-16 10:00:00', $pickupSession->getExpiresAt()->format('Y-m-d H:i:s'));
        self::assertFalse($pickupSession->isUsed());

        $pickupSession->scan(new \DateTimeImmutable('2026-05-15 10:05:00'));
        $pickupSession->confirmByMerchant(new \DateTimeImmutable('2026-05-15 10:06:00'));
        $pickupSession->confirmByCustomer(new \DateTimeImmutable('2026-05-15 10:07:00'));

        self::assertTrue($pickupSession->isUsed());
        self::assertNotNull($pickupSession->getScannedAt());
        self::assertNotNull($pickupSession->getMerchantConfirmedAt());
        self::assertNotNull($pickupSession->getCustomerConfirmedAt());
    }

    public function testPickupSessionRejectsExpiredScan(): void
    {
        $customer = $this->createUser('customer-pickup-expired@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $pickupSession = new PickupSession($order, new \DateTimeImmutable('2026-05-15 10:00:00'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('PICKUP_TOKEN_EXPIRED');

        $pickupSession->scan(new \DateTimeImmutable('2026-05-16 10:00:00'));
    }

    public function testNotificationCanBePersistedAndMarkedRead(): void
    {
        $customer = $this->createUser('customer-notification@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $notification = new Notification(
            $customer,
            'Kadhia prête',
            'القضية جاهزة',
            'Votre commande est prête à retirer.',
            'طلبك جاهز للاستلام.',
            $order,
        );

        $this->entityManager->persist($order);
        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        self::assertFalse($notification->isRead());
        $notification->markRead();
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(Notification::class)->find($notification->getId());

        self::assertInstanceOf(Notification::class, $found);
        self::assertTrue($found->isRead());
        self::assertSame('Kadhia prête', $found->getTitleFr());
        self::assertSame('القضية جاهزة', $found->getTitleAr());
    }

    public function testMarkReadyTransitionCreatesPickupSession(): void
    {
        $customer = $this->createUser('customer-transition-ready@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createPreparingOrderWithPreparedLine($customer, $shop);

        $transitionService = self::getContainer()->get(OrderTransitionService::class);
        self::assertInstanceOf(OrderTransitionService::class, $transitionService);

        $pickupSession = $transitionService->markReady($order);
        $this->entityManager->flush();
        $pickupSessionId = $pickupSession->getId();
        $orderId = $order->getId();
        $this->entityManager->clear();

        $foundOrder = $this->entityManager->getRepository(Order::class)->find($orderId);
        $foundPickupSession = $this->entityManager->getRepository(PickupSession::class)->find($pickupSessionId);

        self::assertInstanceOf(Order::class, $foundOrder);
        self::assertSame(OrderStatus::Ready, $foundOrder->getStatus());
        self::assertInstanceOf(PickupSession::class, $foundPickupSession);
        self::assertSame($foundOrder->getId()->toRfc4122(), $foundPickupSession->getOrder()->getId()->toRfc4122());
    }

    public function testMarkReadyReusesPreExistingPickupSession(): void
    {
        $customer = $this->createUser('customer-idempotent-ready@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createPreparingOrderWithPreparedLine($customer, $shop);

        $preExistingSession = new PickupSession($order);
        $this->entityManager->persist($preExistingSession);
        $this->entityManager->flush();
        $preExistingId = $preExistingSession->getId()->toRfc4122();

        $transitionService = self::getContainer()->get(OrderTransitionService::class);
        self::assertInstanceOf(OrderTransitionService::class, $transitionService);

        $returnedSession = $transitionService->markReady($order);
        $this->entityManager->flush();

        self::assertSame(
            $preExistingId,
            $returnedSession->getId()->toRfc4122(),
            'markReady() must return the pre-existing PickupSession, not create a new one'
        );

        $count = $this->entityManager->getRepository(PickupSession::class)
            ->count(['order' => $order]);
        self::assertSame(1, $count, 'Only one PickupSession must exist for the order');
    }

    public function testFindLatestForUserWithUnreadOnlyFilter(): void
    {
        $customer = $this->createUser('customer-notifications-filter@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $this->entityManager->persist($order);

        $notifA = new Notification($customer, 'A fr', 'A ar', 'Body A fr', 'Body A ar', $order);
        $notifB = new Notification($customer, 'B fr', 'B ar', 'Body B fr', 'Body B ar', $order);
        $notifC = new Notification($customer, 'C fr', 'C ar', 'Body C fr', 'Body C ar', $order);
        $notifB->markRead();

        $this->entityManager->persist($notifA);
        $this->entityManager->persist($notifB);
        $this->entityManager->persist($notifC);
        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var NotificationRepository $repo */
        $repo = $this->entityManager->getRepository(Notification::class);

        $allNotifs = $repo->findLatestForUser($customer);
        self::assertCount(3, $allNotifs);

        $unreadOnly = $repo->findLatestForUser($customer, true);
        self::assertCount(2, $unreadOnly);
        foreach ($unreadOnly as $notif) {
            self::assertFalse($notif->isRead());
        }
    }

    private function createPreparingOrderWithPreparedLine(User $customer, Shop $shop): Order
    {
        $product = $this->createMerchantProduct($shop);
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->submit();
        $order->accept();
        $order->startPreparing();

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd('1.000')
            ->setLineTotalTnd('1.000')
            ->markPrepared(true);
        $order->addLine($line);
        $order->recomputeTotal();

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createMerchantProduct(Shop $shop): MerchantProduct
    {
        $brand = (new Brand())
            ->setCanonicalName('Test Brand '.bin2hex(random_bytes(4)))
            ->setSlug('test-brand-'.bin2hex(random_bytes(4)));
        $category = (new Category())
            ->setNameFr('Catégorie Test '.bin2hex(random_bytes(4)))
            ->setSlug('categorie-test-'.bin2hex(random_bytes(4)));
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit test')
            ->setStatus(ProductReferenceStatus::Approved);
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('1.000');

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }
}
