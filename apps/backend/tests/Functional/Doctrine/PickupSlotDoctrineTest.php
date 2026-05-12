<?php

declare(strict_types=1);

namespace App\Tests\Functional\Doctrine;

use App\Entity\PickupSlot;
use App\Repository\PickupSlotRepository;
use App\Tests\Functional\Api\FunctionalApiTestCase;

final class PickupSlotDoctrineTest extends FunctionalApiTestCase
{
    public function testPickupSlotCanBePersistedAndRetrieved(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable();

        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(5);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(PickupSlot::class)->find($slot->getId());

        self::assertInstanceOf(PickupSlot::class, $found);
        self::assertSame(5, $found->getCapacity());
        self::assertSame(0, $found->getBookedCount());
        self::assertTrue($found->isActive());
        self::assertFalse($found->isFull());
        self::assertSame(5, $found->getAvailableCount());
    }

    public function testBookDecrementsAvailability(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable();

        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(2);

        $slot->book();
        self::assertSame(1, $slot->getBookedCount());
        self::assertSame(1, $slot->getAvailableCount());
        self::assertFalse($slot->isFull());

        $slot->book();
        self::assertTrue($slot->isFull());
        self::assertSame(0, $slot->getAvailableCount());
    }

    public function testBookThrowsWhenFull(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable();

        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(1);

        $slot->book();

        $this->expectException(\RuntimeException::class);
        $slot->book();
    }

    public function testFindAvailableForShopExcludesFullAndInactiveAndPastSlots(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable();

        $available = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(3);

        $full = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+3 hours'))
            ->setEndsAt($now->modify('+4 hours'))
            ->setCapacity(1);
        $full->book();

        $inactive = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+5 hours'))
            ->setEndsAt($now->modify('+6 hours'))
            ->setCapacity(3)
            ->setActive(false);

        $past = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('-2 hours'))
            ->setEndsAt($now->modify('-1 hour'))
            ->setCapacity(3);

        $alreadyStarted = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('-15 minutes'))
            ->setEndsAt($now->modify('+15 minutes'))
            ->setCapacity(3);

        foreach ([$available, $full, $inactive, $past, $alreadyStarted] as $s) {
            $this->entityManager->persist($s);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        $shop = $this->entityManager->getRepository(\App\Entity\Shop::class)->find($shop->getId());
        self::assertNotNull($shop);

        /** @var PickupSlotRepository $repo */
        $repo = $this->entityManager->getRepository(PickupSlot::class);
        $results = $repo->findAvailableForShop($shop);

        self::assertCount(1, $results);
        self::assertSame($available->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
    }
}
