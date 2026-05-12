<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Order;
use App\Entity\OrderLine;
use App\Enum\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    public function testOrderDefaultsToDraftStatus(): void
    {
        self::assertSame(OrderStatus::Draft, (new Order())->getStatus());
    }

    public function testOrderHasUuidId(): void
    {
        self::assertNotNull((new Order())->getId());
    }

    public function testOrderHasTimestamps(): void
    {
        $order = new Order();
        self::assertInstanceOf(\DateTimeImmutable::class, $order->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $order->getUpdatedAt());
    }

    public function testTotalDefaultsToZero(): void
    {
        self::assertSame('0.000', (new Order())->getTotalTnd());
    }

    public function testSubmitTransitionsToDraftToSubmitted(): void
    {
        $order = new Order();
        $order->submit();
        self::assertSame(OrderStatus::Submitted, $order->getStatus());
    }

    public function testSubmitThrowsWhenNotDraft(): void
    {
        $order = new Order();
        $order->submit();
        $this->expectException(\LogicException::class);
        $order->submit();
    }

    public function testAcceptTransitionsSubmittedToAccepted(): void
    {
        $order = new Order();
        $order->submit();
        $order->accept();
        self::assertSame(OrderStatus::Accepted, $order->getStatus());
    }

    public function testAcceptThrowsWhenNotSubmitted(): void
    {
        $this->expectException(\LogicException::class);
        (new Order())->accept();
    }

    public function testRejectTransitionsSubmittedToRejected(): void
    {
        $order = new Order();
        $order->submit();
        $order->reject();
        self::assertSame(OrderStatus::Rejected, $order->getStatus());
    }

    public function testRejectThrowsWhenNotSubmitted(): void
    {
        $this->expectException(\LogicException::class);
        (new Order())->reject();
    }

    public function testStartPreparingTransitionsAcceptedToPreparing(): void
    {
        $order = new Order();
        $order->submit();
        $order->accept();
        $order->startPreparing();
        self::assertSame(OrderStatus::Preparing, $order->getStatus());
    }

    public function testMarkReadyTransitionsPreparingToReady(): void
    {
        $order = new Order();
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $order->markReady();
        self::assertSame(OrderStatus::Ready, $order->getStatus());
    }

    public function testStartPickupTransitionsReadyToPickupPending(): void
    {
        $order = new Order();
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $order->markReady();
        $order->startPickup();
        self::assertSame(OrderStatus::PickupPending, $order->getStatus());
    }

    public function testCompleteTransitionsPickupPendingToCompleted(): void
    {
        $order = new Order();
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $order->markReady();
        $order->startPickup();
        $order->complete();
        self::assertSame(OrderStatus::Completed, $order->getStatus());
    }

    public function testCancelFromDraft(): void
    {
        $order = new Order();
        $order->cancel();
        self::assertSame(OrderStatus::Cancelled, $order->getStatus());
    }

    public function testCancelFromSubmitted(): void
    {
        $order = new Order();
        $order->submit();
        $order->cancel();
        self::assertSame(OrderStatus::Cancelled, $order->getStatus());
    }

    public function testCancelFromAccepted(): void
    {
        $order = new Order();
        $order->submit();
        $order->accept();
        $order->cancel();
        self::assertSame(OrderStatus::Cancelled, $order->getStatus());
    }

    public function testCancelThrowsWhenPreparing(): void
    {
        $order = new Order();
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $this->expectException(\LogicException::class);
        $order->cancel();
    }

    public function testCancelThrowsWhenCompleted(): void
    {
        $order = new Order();
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $order->markReady();
        $order->startPickup();
        $order->complete();
        $this->expectException(\LogicException::class);
        $order->cancel();
    }

    public function testRecomputeTotalSumsLines(): void
    {
        $order = new Order();

        $line1 = (new OrderLine())
            ->setLineTotalTnd('5.500');
        $order->addLine($line1);

        $line2 = (new OrderLine())
            ->setLineTotalTnd('3.250');
        $order->addLine($line2);

        $order->recomputeTotal();

        self::assertSame('8.750', $order->getTotalTnd());
    }

    public function testRecomputeTotalOnEmptyOrderIsZero(): void
    {
        $order = new Order();
        $order->recomputeTotal();
        self::assertSame('0.000', $order->getTotalTnd());
    }

    public function testAddLineMaintainsBidirectionalRelation(): void
    {
        $order = new Order();
        $line = new OrderLine();
        $order->addLine($line);
        self::assertSame($order, $line->getOrder());
        self::assertTrue($order->getLines()->contains($line));
    }

    public function testAddLineIsIdempotent(): void
    {
        $order = new Order();
        $line = new OrderLine();
        $order->addLine($line);
        $order->addLine($line);
        self::assertCount(1, $order->getLines());
    }
}
