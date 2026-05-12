<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\PickupSlot;

final class PickupSlotApiTest extends FunctionalApiTestCase
{
    // GET /api/stores/{storeId}/pickup-slots

    public function testGetPickupSlotsReturnsAvailableSlots(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable();

        $slot1 = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(5);

        $slot2 = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+3 hours'))
            ->setEndsAt($now->modify('+4 hours'))
            ->setCapacity(3);

        $this->entityManager->persist($slot1);
        $this->entityManager->persist($slot2);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(2, $payload);
        self::assertSame($slot1->getId()->toRfc4122(), $payload[0]['id']);
        self::assertSame(5, $payload[0]['capacity']);
        self::assertSame(5, $payload[0]['available_count']);
        self::assertArrayHasKey('starts_at', $payload[0]);
        self::assertArrayHasKey('ends_at', $payload[0]);
        self::assertSame($slot2->getId()->toRfc4122(), $payload[1]['id']);
        self::assertSame(3, $payload[1]['capacity']);
    }

    public function testGetPickupSlotsPartiallyBookedShowsReducedAvailability(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable();

        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(3);
        $slot->book();

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload);
        self::assertSame(3, $payload[0]['capacity']);
        self::assertSame(2, $payload[0]['available_count']);
    }

    public function testGetPickupSlotsExcludesFullSlots(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable();

        $full = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(1);
        $full->book();

        $this->entityManager->persist($full);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(0, $payload);
    }

    public function testGetPickupSlotsExcludesInactiveSlots(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable();

        $inactive = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(3)
            ->setActive(false);

        $this->entityManager->persist($inactive);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(0, $payload);
    }

    public function testGetPickupSlotsExcludesPastSlots(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable();

        $past = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('-2 hours'))
            ->setEndsAt($now->modify('-1 hour'))
            ->setCapacity(3);

        $this->entityManager->persist($past);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(0, $payload);
    }

    public function testGetPickupSlotsOnlyReturnsSlotsBelongingToRequestedShop(): void
    {
        $shop1 = $this->createShop();
        $shop2 = $this->createShop();
        $now = new \DateTimeImmutable();

        $slotShop1 = (new PickupSlot())
            ->setShop($shop1)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(5);

        $slotShop2 = (new PickupSlot())
            ->setShop($shop2)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(5);

        $this->entityManager->persist($slotShop1);
        $this->entityManager->persist($slotShop2);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots', $shop1->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload);
        self::assertSame($slotShop1->getId()->toRfc4122(), $payload[0]['id']);
    }

    public function testGetPickupSlotsUnknownShopReturns404(): void
    {
        $response = $this->requestJson(
            'GET',
            '/api/stores/00000000-0000-0000-0000-000000000099/pickup-slots',
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetPickupSlotsInactiveShopReturns404(): void
    {
        $shop = $this->createShop(active: false);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/stores/%s/pickup-slots', $shop->getId()),
        );

        self::assertSame(404, $response->getStatusCode());
    }
}
