<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\PickupSlot;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    private static int $shopCounter = 0;

    private function makeShop(): Shop
    {
        $i = ++self::$shopCounter;

        return (new Shop())->setName("Shop $i")->setSlug("shop-$i")->setQrCodeToken("qr-$i");
    }

    private function makeProductForShop(Shop $shop): MerchantProduct
    {
        $product = $this->createStub(MerchantProduct::class);
        $product->method('getShop')->willReturn($shop);

        return $product;
    }

    private function makeSlotForShop(Shop $shop): PickupSlot
    {
        $slot = $this->createStub(PickupSlot::class);
        $slot->method('getShop')->willReturn($shop);

        return $slot;
    }

    private function addOrderLine(Order $order, bool $prepared): OrderLine
    {
        $line = (new OrderLine())
            ->setMerchantProduct($this->makeProductForShop($order->getShop()))
            ->setQuantity(1)
            ->setUnitPriceTnd('1.000')
            ->setLineTotalTnd('1.000')
            ->markPrepared($prepared);

        $order->addLine($line);

        return $line;
    }

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

    public function testPartiallyAcceptTransitionsSubmittedToPartiallyAcceptedWithReason(): void
    {
        $order = new Order();
        $order->submit();
        $order->partiallyAccept('Produit indisponible');

        self::assertSame(OrderStatus::PartiallyAccepted, $order->getStatus());
        self::assertSame('Produit indisponible', $order->getRejectionReason());
    }

    public function testPartiallyAcceptStoresNullReasonWhenNoReasonIsProvided(): void
    {
        $order = new Order();
        $order->submit();
        $order->partiallyAccept();

        self::assertSame(OrderStatus::PartiallyAccepted, $order->getStatus());
        self::assertNull($order->getRejectionReason());
    }

    public function testPartiallyAcceptThrowsWhenNotSubmitted(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ORDER_NOT_SUBMITTED');
        (new Order())->partiallyAccept('Produit indisponible');
    }

    public function testResubmitClearsPartialRejectionReason(): void
    {
        $order = new Order();
        $order->submit();
        $order->partiallyAccept('Produit indisponible');
        $order->resubmit();

        self::assertSame(OrderStatus::Submitted, $order->getStatus());
        self::assertNull($order->getRejectionReason());
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
        $shop = $this->makeShop();
        $order = (new Order())->setShop($shop);
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $this->addOrderLine($order, true);
        $order->markReady();
        self::assertSame(OrderStatus::Ready, $order->getStatus());
    }

    public function testMarkReadyThrowsWhenALineIsNotPrepared(): void
    {
        $shop = $this->makeShop();
        $order = (new Order())->setShop($shop);
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $this->addOrderLine($order, true);
        $this->addOrderLine($order, false);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ORDER_LINES_NOT_FULLY_PREPARED');

        $order->markReady();
    }

    public function testMarkReadyThrowsWhenOrderHasNoLines(): void
    {
        $shop = $this->makeShop();
        $order = (new Order())->setShop($shop);
        $order->submit();
        $order->accept();
        $order->startPreparing();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ORDER_LINES_NOT_FULLY_PREPARED');

        $order->markReady();
    }

    public function testStartPickupTransitionsReadyToPickupPending(): void
    {
        $shop = $this->makeShop();
        $order = (new Order())->setShop($shop);
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $this->addOrderLine($order, true);
        $order->markReady();
        $order->startPickup();
        self::assertSame(OrderStatus::PickupPending, $order->getStatus());
    }

    public function testCompleteTransitionsPickupPendingToCompleted(): void
    {
        $shop = $this->makeShop();
        $order = (new Order())->setShop($shop);
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $this->addOrderLine($order, true);
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
        $shop = $this->makeShop();
        $order = (new Order())->setShop($shop);
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $this->addOrderLine($order, true);
        $order->markReady();
        $order->startPickup();
        $order->complete();
        $this->expectException(\LogicException::class);
        $order->cancel();
    }

    public function testRecomputeTotalSumsLines(): void
    {
        $shop = $this->makeShop();
        $order = (new Order())->setShop($shop);

        $line1 = (new OrderLine())
            ->setMerchantProduct($this->makeProductForShop($shop))
            ->setLineTotalTnd('5.500');
        $order->addLine($line1);

        $line2 = (new OrderLine())
            ->setMerchantProduct($this->makeProductForShop($shop))
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
        $shop = $this->makeShop();
        $order = (new Order())->setShop($shop);
        $line = (new OrderLine())->setMerchantProduct($this->makeProductForShop($shop));
        $order->addLine($line);
        self::assertSame($order, $line->getOrder());
        self::assertTrue($order->getLines()->contains($line));
    }

    public function testAddLineIsIdempotent(): void
    {
        $shop = $this->makeShop();
        $order = (new Order())->setShop($shop);
        $line = (new OrderLine())->setMerchantProduct($this->makeProductForShop($shop));
        $order->addLine($line);
        $order->addLine($line);
        self::assertCount(1, $order->getLines());
    }

    public function testSetPickupSlotNullIsAlwaysAllowed(): void
    {
        $shop = $this->makeShop();
        $order = (new Order())->setShop($shop);
        $order->setPickupSlot(null);
        self::assertNull($order->getPickupSlot());
    }

    public function testSetPickupSlotThrowsWhenShopMismatch(): void
    {
        $shop1 = $this->makeShop();
        $shop2 = $this->makeShop();
        $order = (new Order())->setShop($shop1);
        $this->expectException(\LogicException::class);
        $order->setPickupSlot($this->makeSlotForShop($shop2));
    }

    public function testAddLineThrowsWhenShopMismatch(): void
    {
        $shop1 = $this->makeShop();
        $shop2 = $this->makeShop();
        $order = (new Order())->setShop($shop1);
        $line = (new OrderLine())->setMerchantProduct($this->makeProductForShop($shop2));
        $this->expectException(\LogicException::class);
        $order->addLine($line);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function makeReadyOrder(): Order
    {
        $shop = $this->makeShop();
        $order = (new Order())->setCustomer(new User())->setShop($shop);
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $product = $this->makeProductForShop($shop);
        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd('1.000')
            ->setLineTotalTnd('1.000')
            ->markPrepared(true);
        $order->addLine($line);
        $order->recomputeTotal();
        $order->markReady();

        return $order;
    }

    // ---------------------------------------------------------------------------
    // pickupCode
    // ---------------------------------------------------------------------------

    public function testMarkReadyGeneratesPickupCode(): void
    {
        $order = $this->makeReadyOrder();
        self::assertNotNull($order->getPickupCode());
        self::assertMatchesRegularExpression('/^\d{4}$/', (string) $order->getPickupCode());
    }

    public function testRedeemByCodeTransitionsToCompleted(): void
    {
        $order = $this->makeReadyOrder();
        $code = $order->getPickupCode();
        self::assertNotNull($code);
        $order->redeemByCode($code);
        self::assertSame(OrderStatus::Completed, $order->getStatus());
        self::assertNull($order->getPickupCode());
    }

    public function testRedeemByCodeThrowsOnWrongCode(): void
    {
        $order = $this->makeReadyOrder();
        $correctCode = $order->getPickupCode() ?? '0000';
        $wrongCode = '1234' === $correctCode ? '5678' : '1234';
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('PICKUP_CODE_INVALID');
        $order->redeemByCode($wrongCode);
    }

    public function testRedeemByCodeThrowsWhenNotReady(): void
    {
        $shop = $this->makeShop();
        $order = (new Order())->setCustomer(new User())->setShop($shop);
        $order->submit();
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ORDER_NOT_READY');
        $order->redeemByCode('1234');
    }

    public function testCompleteManuallyTransitionsToCompleted(): void
    {
        $order = $this->makeReadyOrder();
        $order->completeManually();
        self::assertSame(OrderStatus::Completed, $order->getStatus());
    }

    public function testCompleteManuallyThrowsWhenNotReady(): void
    {
        $shop = $this->makeShop();
        $order = (new Order())->setCustomer(new User())->setShop($shop);
        $order->submit();
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ORDER_NOT_READY');
        $order->completeManually();
    }
}
