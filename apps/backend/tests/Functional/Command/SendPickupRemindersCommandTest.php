<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Service\NotificationService;
use App\Service\PickupReminderSender;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use App\Tests\Functional\OrderPickupFixtureTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class SendPickupRemindersCommandTest extends FunctionalApiTestCase
{
    use OrderPickupFixtureTrait;

    public function testSendsDueReminderForReadyOrderInWindow(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $order = $this->createReadyOrder($now->modify('+60 minutes'));

        $sent = $this->buildSender($now)->sendDueReminders();

        self::assertSame(1, $sent);

        $notifications = $this->findPickupReminders($order);
        self::assertCount(1, $notifications);
        self::assertSame($order->getCustomer()->getId(), $notifications[0]->getUser()->getId());
        self::assertSame('pickup_reminder', $notifications[0]->getType());
        self::assertSame('Rappel de retrait', $notifications[0]->getTitleFr());
        self::assertSame('تذكير بالاستلام', $notifications[0]->getTitleAr());
        self::assertStringContainsString($order->getShop()->getName(), $notifications[0]->getBodyFr());
        self::assertStringContainsString($order->getShop()->getName(), $notifications[0]->getBodyAr());
    }

    public function testDoesNotSendReminderForOrderOutsideWindow(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $order = $this->createReadyOrder($now->modify('+120 minutes'));

        $sent = $this->buildSender($now)->sendDueReminders();

        self::assertSame(0, $sent);
        self::assertCount(0, $this->findPickupReminders($order));
    }

    public function testDoesNotSendDuplicateReminder(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $order = $this->createReadyOrder($now->modify('+60 minutes'));
        $sender = $this->buildSender($now);

        $sender->sendDueReminders();
        $sender->sendDueReminders();

        // The unique constraint on (order_id, type) ensures only one reminder per order.
        self::assertCount(1, $this->findPickupReminders($order));
    }

    public function testSendsReminderForPickupPendingOrderInWindow(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $order = $this->createReadyOrder($now->modify('+60 minutes'));
        $order->startPickup();
        $this->entityManager->flush();

        $sent = $this->buildSender($now)->sendDueReminders();

        self::assertSame(1, $sent);
        self::assertCount(1, $this->findPickupReminders($order));
    }

    public function testDoesNotSendReminderForCancelledOrder(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $customer = $this->createUser('customer-cancel-'.Uuid::v4().'@test.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-cancel-'.Uuid::v4().'@test.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop, $now->modify('+60 minutes'));
        $order = (new Order())->setCustomer($customer)->setShop($shop)->setPickupSlot($slot);
        $order->submit();
        $order->cancel();
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $sent = $this->buildSender($now)->sendDueReminders();

        self::assertSame(0, $sent);
    }

    public function testDoesNotSendReminderForCompletedOrder(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $order = $this->createReadyOrder($now->modify('+60 minutes'));
        $order->startPickup();
        $order->complete();
        $this->entityManager->flush();

        $sent = $this->buildSender($now)->sendDueReminders();

        self::assertSame(0, $sent);
    }

    public function testNotificationBodyContainsShopNameAndSlotTime(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $order = $this->createReadyOrder($now->modify('+60 minutes'));

        $this->buildSender($now)->sendDueReminders();

        $notifications = $this->findPickupReminders($order);
        self::assertCount(1, $notifications);

        $shopName = $order->getShop()->getName();
        self::assertStringContainsString($shopName, $notifications[0]->getBodyFr());
        self::assertStringContainsString($shopName, $notifications[0]->getBodyAr());
    }

    public function testCommandOutputShowsSentCount(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 10:00:00');
        $this->createReadyOrder($now->modify('+60 minutes'));
        $this->createReadyOrder($now->modify('+62 minutes'));

        $sent = $this->buildSender($now)->sendDueReminders();

        self::assertSame(2, $sent);
    }

    private function buildSender(\DateTimeImmutable $now): PickupReminderSender
    {
        return new PickupReminderSender(
            $this->entityManager->getRepository(Order::class),
            self::getContainer()->get(NotificationService::class),
            $this->entityManager,
            new MockClock($now),
        );
    }

    /**
     * @return list<Notification>
     */
    private function findPickupReminders(Order $order): array
    {
        return $this->entityManager->getRepository(Notification::class)->findBy([
            'order' => $order,
            'type' => 'pickup_reminder',
        ]);
    }

    private function createReadyOrder(\DateTimeImmutable $slotStartsAt): Order
    {
        $customer = $this->createUser('customer-cmd-'.Uuid::v4().'@test.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-cmd-'.Uuid::v4().'@test.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop, $slotStartsAt);
        $product = $this->createMerchantProduct($shop);

        $order = (new Order())->setCustomer($customer)->setShop($shop)->setPickupSlot($slot);
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
}
