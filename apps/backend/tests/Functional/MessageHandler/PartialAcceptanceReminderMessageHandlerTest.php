<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Enum\OrderStatus;
use App\Message\PartialAcceptanceReminderMessage;
use App\MessageHandler\PartialAcceptanceReminderMessageHandler;
use App\Service\NotificationService;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use App\Tests\Functional\OrderPickupFixtureTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class PartialAcceptanceReminderMessageHandlerTest extends FunctionalApiTestCase
{
    use OrderPickupFixtureTrait;

    public function testHandlerCreatesReminderNotificationInReminderWindow(): void
    {
        $order = $this->createPartiallyAcceptedOrder(new \DateTimeImmutable('2026-05-16 14:00:00'));

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:30:00'))(
            new PartialAcceptanceReminderMessage($order->getId()->toRfc4122())
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::PartiallyAccepted, $updatedOrder->getStatus());

        $notifications = $this->findReminderNotifications($updatedOrder);
        self::assertCount(1, $notifications);
        self::assertSame('Réponse nécessaire', $notifications[0]->getTitleFr());
        self::assertSame('يلزم الرد', $notifications[0]->getTitleAr());
    }

    public function testHandlerDoesNothingBeforeReminderWindow(): void
    {
        $order = $this->createPartiallyAcceptedOrder(new \DateTimeImmutable('2026-05-16 15:00:00'));

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:30:00'))(
            new PartialAcceptanceReminderMessage($order->getId()->toRfc4122())
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertCount(0, $this->findReminderNotifications($updatedOrder));
    }

    public function testHandlerDoesNothingInsideExpirationWindow(): void
    {
        $order = $this->createPartiallyAcceptedOrder(new \DateTimeImmutable('2026-05-16 11:30:00'));

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:00:00'))(
            new PartialAcceptanceReminderMessage($order->getId()->toRfc4122())
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertCount(0, $this->findReminderNotifications($updatedOrder));
    }

    public function testHandlerDoesNothingAfterResubmission(): void
    {
        $order = $this->createPartiallyAcceptedOrder(new \DateTimeImmutable('2026-05-16 14:00:00'));
        $order->resubmit();
        $this->entityManager->flush();

        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:30:00'))(
            new PartialAcceptanceReminderMessage($order->getId()->toRfc4122())
        );

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertSame(OrderStatus::Submitted, $updatedOrder->getStatus());
        self::assertCount(0, $this->findReminderNotifications($updatedOrder));
    }

    public function testHandlerIsIdempotent(): void
    {
        $order = $this->createPartiallyAcceptedOrder(new \DateTimeImmutable('2026-05-16 14:00:00'));
        $handler = $this->createHandler(new \DateTimeImmutable('2026-05-16 10:30:00'));
        $message = new PartialAcceptanceReminderMessage($order->getId()->toRfc4122());

        $handler($message);
        $handler($message);

        $this->entityManager->clear();
        $updatedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updatedOrder);
        self::assertCount(1, $this->findReminderNotifications($updatedOrder));
    }

    public function testInvalidOrderIdDoesNothing(): void
    {
        $this->createHandler(new \DateTimeImmutable('2026-05-16 10:30:00'))(
            new PartialAcceptanceReminderMessage('not-a-uuid')
        );

        self::assertSame(0, $this->entityManager->getRepository(Notification::class)->count([]));
    }

    private function createHandler(\DateTimeImmutable $now): PartialAcceptanceReminderMessageHandler
    {
        return new PartialAcceptanceReminderMessageHandler(
            $this->entityManager->getRepository(Order::class),
            self::getContainer()->get(NotificationService::class),
            $this->entityManager,
            new MockClock($now),
            14400,
            7200,
        );
    }

    private function createPartiallyAcceptedOrder(\DateTimeImmutable $slotStartsAt): Order
    {
        $customer = $this->createUser('customer-partial-reminder-'.Uuid::v4().'@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-partial-reminder-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $slot = $this->createPickupSlot($shop, $slotStartsAt);
        $product = $this->createMerchantProduct($shop);

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setPickupSlot($slot);

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd($product->getPriceTnd())
            ->setLineTotalTnd($product->getPriceTnd());
        $order->addLine($line);
        $order->recomputeTotal();
        $order->submit();
        $order->partiallyAccept('Rupture');

        $this->entityManager->persist($order);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $order;
    }

    /**
     * @return list<Notification>
     */
    private function findReminderNotifications(Order $order): array
    {
        return $this->entityManager->getRepository(Notification::class)->findBy([
            'order' => $order,
            'type' => NotificationService::TYPE_PARTIAL_ACCEPTANCE_REMINDER,
        ]);
    }
}
