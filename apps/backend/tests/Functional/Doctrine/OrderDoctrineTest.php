<?php

declare(strict_types=1);

namespace App\Tests\Functional\Doctrine;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\PickupSlot;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\OrderStatus;
use App\Tests\Functional\Api\FunctionalApiTestCase;

final class OrderDoctrineTest extends FunctionalApiTestCase
{
    private static int $refCounter = 0;

    private function makeMerchantProduct(string $priceTnd = '2.500', ?Shop $shop = null): MerchantProduct
    {
        $shop ??= $this->createShop();
        $i = ++self::$refCounter;
        $brand = (new Brand())->setCanonicalName("Brand $i")->setSlug("brand-order-$i");
        $category = (new Category())->setNameFr("Cat $i")->setSlug("cat-order-$i");
        $ref = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr("Produit $i");

        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd($priceTnd);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ref);
        $this->entityManager->persist($product);

        return $product;
    }

    public function testOrderCanBePersistedAndRetrieved(): void
    {
        $customer = $this->createUser('customer-order@example.com', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);

        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(Order::class)->find($order->getId());

        self::assertInstanceOf(Order::class, $found);
        self::assertSame(OrderStatus::Draft, $found->getStatus());
        self::assertEqualsWithDelta(0.0, (float) $found->getTotalTnd(), 0.001);
        self::assertNull($found->getNotes());
        self::assertNull($found->getPickupSlot());
        self::assertNull($found->getKadhia());
    }

    public function testOrderWithLinesCanBePersistedAndRetrieved(): void
    {
        $customer = $this->createUser('customer-lines@example.com', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->makeMerchantProduct('3.000', $shop);

        $this->entityManager->flush();

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(2)
            ->setUnitPriceTnd('3.000')
            ->setLineTotalTnd('6.000');

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->addLine($line);
        $order->recomputeTotal();

        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(Order::class)->find($order->getId());

        self::assertInstanceOf(Order::class, $found);
        self::assertCount(1, $found->getLines());
        self::assertEqualsWithDelta(6.0, (float) $found->getTotalTnd(), 0.001);

        $foundLine = $found->getLines()->first();
        self::assertInstanceOf(OrderLine::class, $foundLine);
        self::assertSame(2, $foundLine->getQuantity());
        self::assertEqualsWithDelta(3.0, (float) $foundLine->getUnitPriceTnd(), 0.001);
        self::assertEqualsWithDelta(6.0, (float) $foundLine->getLineTotalTnd(), 0.001);
    }

    public function testOrderPickupSlotCanBeSetAndRetrieved(): void
    {
        $customer = $this->createUser('customer-slot@example.com', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $now = new \DateTimeImmutable();

        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(5);

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setPickupSlot($slot);

        $this->entityManager->persist($slot);
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(Order::class)->find($order->getId());

        self::assertInstanceOf(Order::class, $found);
        self::assertNotNull($found->getPickupSlot());
        self::assertSame($slot->getId()->toRfc4122(), $found->getPickupSlot()->getId()->toRfc4122());
    }

    public function testOrderLinesAreCascadeDeletedWithOrder(): void
    {
        $customer = $this->createUser('customer-cascade@example.com', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->makeMerchantProduct('2.500', $shop);
        $this->entityManager->flush();

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd('2.500')
            ->setLineTotalTnd('2.500');

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->addLine($line);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $orderId = $order->getId();
        $lineId = $line->getId();

        $this->entityManager->remove($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNull($this->entityManager->getRepository(Order::class)->find($orderId));
        self::assertNull($this->entityManager->getRepository(OrderLine::class)->find($lineId));
    }
}
