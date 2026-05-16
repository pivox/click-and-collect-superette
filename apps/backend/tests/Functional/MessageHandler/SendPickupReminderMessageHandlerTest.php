<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\PickupSession;
use App\Entity\PickupSlot;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\OrderStatus;
use App\Enum\ProductReferenceStatus;
use App\Message\SendPickupReminderMessage;
use App\MessageHandler\SendPickupReminderMessageHandler;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class SendPickupReminderMessageHandlerTest extends FunctionalApiTestCase
{
    public function testHandlerCreatesCustomerNotificationForReadyOrder(): void
    {
        $order = $this->createReadyOrderWithPickupSession(new \DateTimeImmutable('2026-05-16 11:00:00'));
        $handler = $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'));

        $handler(new SendPickupReminderMessage($order->getId()->toRfc4122()));

        $notifications = $this->findNotifications($order);
        self::assertCount(1, $notifications);
        self::assertSame($order->getCustomer()->getId(), $notifications[0]->getUser()->getId());
        self::assertSame('pickup_reminder', $notifications[0]->getType());
        self::assertSame('Rappel de retrait', $notifications[0]->getTitleFr());
        self::assertSame('تذكير بالاستلام', $notifications[0]->getTitleAr());
        self::assertSame('Votre Kadhia est prête. Pensez à la retirer pendant votre créneau.', $notifications[0]->getBodyFr());
        self::assertSame('القاضية واجدة. تذكروا استلامها خلال الموعد المحدد.', $notifications[0]->getBodyAr());
    }

    public function testHandlerDoesNothingBeforeReminderWindow(): void
    {
        $order = $this->createReadyOrderWithPickupSession(new \DateTimeImmutable('2026-05-16 12:00:00'));
        $handler = $this->createHandler(new \DateTimeImmutable('2026-05-16 10:30:00'));

        $handler(new SendPickupReminderMessage($order->getId()->toRfc4122()));

        self::assertCount(0, $this->findNotifications($order));
    }

    public function testHandlerDoesNothingForPickupPendingOrder(): void
    {
        $order = $this->createReadyOrderWithPickupSession(new \DateTimeImmutable('2026-05-16 11:00:00'));
        $order->startPickup();
        $this->entityManager->flush();

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(new SendPickupReminderMessage($order->getId()->toRfc4122()));

        self::assertCount(0, $this->findNotifications($order));
    }

    public function testHandlerDoesNothingForCompletedOrder(): void
    {
        $order = $this->createReadyOrderWithPickupSession(new \DateTimeImmutable('2026-05-16 11:00:00'));
        $order->startPickup();
        $order->complete();
        $this->entityManager->flush();

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(new SendPickupReminderMessage($order->getId()->toRfc4122()));

        self::assertCount(0, $this->findNotifications($order));
    }

    public function testHandlerDoesNothingForCancelledOrder(): void
    {
        $order = $this->createOrderWithStatus(OrderStatus::Cancelled, new \DateTimeImmutable('2026-05-16 11:00:00'));

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(new SendPickupReminderMessage($order->getId()->toRfc4122()));

        self::assertCount(0, $this->findNotifications($order));
    }

    public function testHandlerDoesNothingForRejectedOrder(): void
    {
        $order = $this->createOrderWithStatus(OrderStatus::Rejected, new \DateTimeImmutable('2026-05-16 11:00:00'));

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(new SendPickupReminderMessage($order->getId()->toRfc4122()));

        self::assertCount(0, $this->findNotifications($order));
    }

    public function testHandlerDoesNothingWhenPickupSessionDoesNotExist(): void
    {
        $order = $this->createReadyOrder(new \DateTimeImmutable('2026-05-16 11:00:00'));

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(new SendPickupReminderMessage($order->getId()->toRfc4122()));

        self::assertCount(0, $this->findNotifications($order));
    }

    public function testHandlerDoesNothingWhenPickupSessionIsUsed(): void
    {
        $order = $this->createReadyOrderWithPickupSession(new \DateTimeImmutable('2026-05-16 11:00:00'));
        $session = $this->entityManager->getRepository(PickupSession::class)->findOneBy(['order' => $order]);
        self::assertNotNull($session);
        $this->setPrivateProperty($session, 'used', true);
        $this->entityManager->flush();

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(new SendPickupReminderMessage($order->getId()->toRfc4122()));

        self::assertCount(0, $this->findNotifications($order));
    }

    public function testHandlerDoesNothingWhenPickupSessionIsScanned(): void
    {
        $order = $this->createReadyOrderWithPickupSession(new \DateTimeImmutable('2026-05-16 11:00:00'));
        $session = $this->entityManager->getRepository(PickupSession::class)->findOneBy(['order' => $order]);
        self::assertNotNull($session);
        $session->scan(new \DateTimeImmutable('2026-05-16 10:10:00'));
        $this->entityManager->flush();

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(new SendPickupReminderMessage($order->getId()->toRfc4122()));

        self::assertCount(0, $this->findNotifications($order));
    }

    public function testHandlerDoesNotCreateTwoRemindersForSameOrder(): void
    {
        $order = $this->createReadyOrderWithPickupSession(new \DateTimeImmutable('2026-05-16 11:00:00'));
        $handler = $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'));
        $message = new SendPickupReminderMessage($order->getId()->toRfc4122());

        $handler($message);
        $handler($message);

        self::assertCount(1, $this->findNotifications($order));
    }

    public function testReminderIsVisibleOnlyToCustomerOwnerViaNotificationApi(): void
    {
        $otherCustomer = $this->createUser('other-reminder-'.Uuid::v4().'@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createReadyOrderWithPickupSession(new \DateTimeImmutable('2026-05-16 11:00:00'));
        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(new SendPickupReminderMessage($order->getId()->toRfc4122()));

        $ownerResponse = $this->requestJson('GET', '/api/me/notifications', null, $order->getCustomer());
        $ownerPayload = $this->decodeJson($ownerResponse);
        self::assertSame(1, $ownerPayload['total']);
        self::assertSame('Rappel de retrait', $ownerPayload['items'][0]['title_fr']);
        self::assertSame('تذكير بالاستلام', $ownerPayload['items'][0]['title_ar']);
        self::assertSame('Votre Kadhia est prête. Pensez à la retirer pendant votre créneau.', $ownerPayload['items'][0]['body_fr']);
        self::assertSame('القاضية واجدة. تذكروا استلامها خلال الموعد المحدد.', $ownerPayload['items'][0]['body_ar']);

        $otherResponse = $this->requestJson('GET', '/api/me/notifications', null, $otherCustomer);
        $otherPayload = $this->decodeJson($otherResponse);
        self::assertSame(0, $otherPayload['total']);
    }

    private function createHandler(\DateTimeImmutable $now): SendPickupReminderMessageHandler
    {
        return new SendPickupReminderMessageHandler(
            $this->entityManager->getRepository(Order::class),
            $this->entityManager->getRepository(PickupSession::class),
            self::getContainer()->get(\App\Service\NotificationService::class),
            $this->entityManager,
            new MockClock($now),
        );
    }

    /**
     * @return list<Notification>
     */
    private function findNotifications(Order $order): array
    {
        return $this->entityManager->getRepository(Notification::class)->findBy([
            'order' => $order,
            'type' => 'pickup_reminder',
        ]);
    }

    private function createReadyOrderWithPickupSession(\DateTimeImmutable $slotStartsAt): Order
    {
        $order = $this->createReadyOrder($slotStartsAt);
        $this->entityManager->persist(new PickupSession($order, new \DateTimeImmutable('2026-05-16 09:00:00')));
        $this->entityManager->flush();

        return $order;
    }

    private function createReadyOrder(\DateTimeImmutable $slotStartsAt): Order
    {
        $customer = $this->createUser('customer-handler-'.Uuid::v4().'@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-handler-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop, $slotStartsAt);
        $product = $this->createMerchantProduct($shop);

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setPickupSlot($slot);
        $order->submit();
        $order->accept();
        $order->startPreparing();

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd($product->getPriceTnd())
            ->setLineTotalTnd($product->getPriceTnd())
            ->markPrepared(true);
        $order->addLine($line);
        $order->recomputeTotal();
        $order->markReady();

        $this->entityManager->persist($order);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $order;
    }

    private function createOrderWithStatus(OrderStatus $status, \DateTimeImmutable $slotStartsAt): Order
    {
        $customer = $this->createUser('customer-status-'.$status->value.'-'.Uuid::v4().'@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-status-'.$status->value.'-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop, $slotStartsAt);

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setPickupSlot($slot);
        $order->submit();

        if (OrderStatus::Rejected === $status) {
            $order->reject('Rupture');
        } elseif (OrderStatus::Cancelled === $status) {
            $order->cancel();
        } else {
            throw new \InvalidArgumentException('Unsupported status fixture.');
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createPickupSlot(Shop $shop, \DateTimeImmutable $startsAt): PickupSlot
    {
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($startsAt)
            ->setEndsAt($startsAt->modify('+1 hour'))
            ->setCapacity(5);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }

    private function createMerchantProduct(Shop $shop): MerchantProduct
    {
        $id = Uuid::v4()->toRfc4122();
        $brand = (new Brand())
            ->setCanonicalName('Brand Handler '.$id)
            ->setSlug('brand-handler-'.$id);
        $category = (new Category())
            ->setNameFr('Cat Handler '.$id)
            ->setSlug('cat-handler-'.$id);
        $ref = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit rappel handler')
            ->setStatus(ProductReferenceStatus::Approved);
        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd('2.000');

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ref);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }
}
