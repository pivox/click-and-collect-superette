<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\PickupSlot;
use App\Service\PickupSlotDisplayTime;

final class PickupSlotApiTest extends FunctionalApiTestCase
{
    // GET /api/stores/{storeId}/pickup-slots

    public function testGetPickupSlotsReturnsAvailableSlots(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));

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
        self::assertFalse(array_is_list($payload));
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertArrayHasKey('items', $payload);
        self::assertCount(2, $payload['items']);
        self::assertSame($slot1->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame(5, $payload['items'][0]['capacity']);
        self::assertSame(5, $payload['items'][0]['available_count']);
        self::assertArrayHasKey('starts_at', $payload['items'][0]);
        self::assertArrayHasKey('ends_at', $payload['items'][0]);
        self::assertSame($slot2->getId()->toRfc4122(), $payload['items'][1]['id']);
        self::assertSame(3, $payload['items'][1]['capacity']);
    }

    public function testGetPickupSlotsPartiallyBookedShowsReducedAvailability(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));

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
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertCount(1, $payload['items']);
        self::assertSame(3, $payload['items'][0]['capacity']);
        self::assertSame(2, $payload['items'][0]['available_count']);
    }

    public function testGetPickupSlotsExcludesFullSlots(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));

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
        self::assertCount(0, $payload['items']);
    }

    public function testGetPickupSlotsExcludesInactiveSlots(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));

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
        self::assertCount(0, $payload['items']);
    }

    public function testGetPickupSlotsExcludesPastSlots(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));

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
        self::assertCount(0, $payload['items']);
    }

    public function testGetPickupSlotsExcludesAlreadyStartedSlots(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));

        $alreadyStarted = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('-15 minutes'))
            ->setEndsAt($now->modify('+15 minutes'))
            ->setCapacity(3);

        $future = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+1 hour'))
            ->setEndsAt($now->modify('+2 hours'))
            ->setCapacity(3);

        $this->entityManager->persist($alreadyStarted);
        $this->entityManager->persist($future);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertSame($future->getId()->toRfc4122(), $payload['items'][0]['id']);
    }

    public function testGetPickupSlotsExcludesReloadedLocalClockSlotAlreadyStartedInTunisia(): void
    {
        $shop = $this->createShop();
        $timezone = new \DateTimeZone('Africa/Tunis');
        $now = new \DateTimeImmutable('now', $timezone);

        $alreadyStarted = $this->createPickupSlot(
            $shop,
            $now->modify('-30 minutes'),
            $now->modify('+30 minutes'),
            3,
        );
        $future = $this->createPickupSlot(
            $shop,
            $now->modify('+1 hour'),
            $now->modify('+2 hours'),
            3,
        );
        $alreadyStartedId = $alreadyStarted->getId()->toRfc4122();
        $futureId = $future->getId()->toRfc4122();

        $this->entityManager->clear();
        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertSame($futureId, $payload['items'][0]['id']);
        self::assertNotSame($alreadyStartedId, $payload['items'][0]['id']);
    }

    public function testGetPickupSlotsExcludesSlotsLongerThanOneHour(): void
    {
        $shop = $this->createShop();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));

        $longSlot = $this->createPickupSlot($shop, $now->modify('+1 hour'), $now->modify('+7 hours'), 3);
        $oneHourSlot = $this->createPickupSlot($shop, $now->modify('+8 hours'), $now->modify('+9 hours'), 3);

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertSame($oneHourSlot->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertNotSame($longSlot->getId()->toRfc4122(), $payload['items'][0]['id']);
    }

    public function testGetPickupSlotsFiltersByRequestedLocalDate(): void
    {
        $shop = $this->createShop();
        $timezone = new \DateTimeZone('Africa/Tunis');

        $requestedDaySlot = $this->createPickupSlot(
            $shop,
            new \DateTimeImmutable('2030-05-29 10:00:00', $timezone),
            new \DateTimeImmutable('2030-05-29 11:00:00', $timezone),
            3,
        );
        $this->createPickupSlot(
            $shop,
            new \DateTimeImmutable('2030-05-30 10:00:00', $timezone),
            new \DateTimeImmutable('2030-05-30 11:00:00', $timezone),
            3,
        );

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots?date=2030-05-29', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertSame($requestedDaySlot->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('2030-05-29T10:00:00+01:00', $payload['items'][0]['starts_at']);
    }

    public function testGetPickupSlotsDateTodayKeywordReturnsOnlyTodaySlots(): void
    {
        $shop = $this->createShop();
        $timezone = new \DateTimeZone('Africa/Tunis');
        $now = new \DateTimeImmutable('now', $timezone);

        $todaySlot = $this->createPickupSlot(
            $shop,
            $now->modify('+5 minutes'),
            $now->modify('+65 minutes'),
            3,
        );
        $tomorrowDate = new \DateTimeImmutable('tomorrow midnight', $timezone);
        $this->createPickupSlot($shop, $tomorrowDate->modify('+1 hour'), $tomorrowDate->modify('+2 hours'), 3);

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots?date=today', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertSame($todaySlot->getId()->toRfc4122(), $payload['items'][0]['id']);
    }

    public function testGetPickupSlotsInvalidCalendarDateReturns400(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots?date=2030-02-30', $shop->getId()));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testGetPickupSlotsOnlyReturnsSlotsBelongingToRequestedShop(): void
    {
        $shop1 = $this->createShop();
        $shop2 = $this->createShop();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));

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
        self::assertCount(1, $payload['items']);
        self::assertSame($slotShop1->getId()->toRfc4122(), $payload['items'][0]['id']);
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

    // Merchant CRUD /api/merchant/stores/{storeId}/pickup-slots

    public function testMerchantOwnerListsPickupSlotsForOwnStore(): void
    {
        $merchant = $this->createUser('merchant-slots-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $now = new \DateTimeImmutable();
        $active = $this->createPickupSlot($shop, $now->modify('+1 hour'), $now->modify('+2 hours'), 4);
        $inactive = $this->createPickupSlot($shop, $now->modify('+3 hours'), $now->modify('+4 hours'), 2, false);

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/pickup-slots', $shop->getId()), user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(2, $payload);
        self::assertSame($active->getId()->toRfc4122(), $payload[0]['id']);
        self::assertSame(4, $payload[0]['capacity']);
        self::assertSame(0, $payload[0]['booked_count']);
        self::assertTrue($payload[0]['is_active']);
        self::assertSame($inactive->getId()->toRfc4122(), $payload[1]['id']);
        self::assertFalse($payload[1]['is_active']);
    }

    public function testMerchantPickupSlotCollectionRejectsOtherMerchantClientAndAnonymous(): void
    {
        $owner = $this->createUser('merchant-slots-list-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-slots-list-other@example.test', ['ROLE_MERCHANT']);
        $client = $this->createUser('client-slots-list@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($owner);

        $otherMerchantResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/pickup-slots', $shop->getId()), user: $otherMerchant);
        $clientResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/pickup-slots', $shop->getId()), user: $client);
        $anonymousResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/pickup-slots', $shop->getId()));

        self::assertSame(403, $otherMerchantResponse->getStatusCode());
        self::assertSame(403, $clientResponse->getStatusCode());
        self::assertContains($anonymousResponse->getStatusCode(), [401, 403]);
    }

    public function testMerchantOwnerCreatesValidPickupSlot(): void
    {
        $merchant = $this->createUser('merchant-slots-create-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $startsAt = new \DateTimeImmutable('+1 day 10:00');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/pickup-slots', $shop->getId()),
            $this->validMerchantPickupSlotPayload($startsAt, $startsAt->modify('+1 hour'), 6),
            $merchant,
        );

        self::assertSame(201, $response->getStatusCode());
        $slots = $this->entityManager->getRepository(PickupSlot::class)->findBy(['shop' => $shop]);
        self::assertCount(1, $slots);
        self::assertSame(6, $slots[0]->getCapacity());
        self::assertTrue($slots[0]->isActive());
    }

    public function testMerchantPickupSlotCreatePreservesManualUtcInstantAsTunisiaLocalTime(): void
    {
        $merchant = $this->createUser('merchant-slots-create-utc@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $storeId = $shop->getId()->toRfc4122();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/pickup-slots', $storeId),
            [
                'starts_at' => '2030-05-28T16:00:00+00:00',
                'ends_at' => '2030-05-28T17:00:00+00:00',
                'capacity' => 6,
            ],
            $merchant,
        );

        self::assertSame(201, $response->getStatusCode());

        $this->entityManager->clear();
        $listResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/pickup-slots', $storeId), user: $merchant);
        self::assertSame(200, $listResponse->getStatusCode());
        $payload = $this->decodeJson($listResponse);

        self::assertCount(1, $payload);
        self::assertSame('2030-05-28T17:00:00+01:00', $payload[0]['starts_at']);
        self::assertSame('2030-05-28T18:00:00+01:00', $payload[0]['ends_at']);
    }

    public function testMerchantPickupSlotCreateRejectsUtcManualOverlapWithStoredLocalClock(): void
    {
        $merchant = $this->createUser('merchant-slots-create-utc-overlap@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $storeId = $shop->getId()->toRfc4122();
        $timezone = new \DateTimeZone('Africa/Tunis');
        $this->createPickupSlot(
            $shop,
            new \DateTimeImmutable('2030-05-28 17:00:00', $timezone),
            new \DateTimeImmutable('2030-05-28 18:00:00', $timezone),
            6,
        );

        $this->entityManager->clear();
        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/pickup-slots', $storeId),
            [
                'starts_at' => '2030-05-28T16:00:00+00:00',
                'ends_at' => '2030-05-28T17:00:00+00:00',
                'capacity' => 6,
            ],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_OVERLAPS_EXISTING_SLOT', (string) $response->getContent());
    }

    public function testMerchantPickupSlotCreateRejectsOtherMerchant(): void
    {
        $owner = $this->createUser('merchant-slots-create-forbidden-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-slots-create-forbidden-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);
        $startsAt = new \DateTimeImmutable('+1 day 10:00');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/pickup-slots', $shop->getId()),
            $this->validMerchantPickupSlotPayload($startsAt, $startsAt->modify('+1 hour'), 6),
            $otherMerchant,
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(PickupSlot::class)->count(['shop' => $shop]));
    }

    public function testMerchantPickupSlotCreateRejectsInvalidPayload(): void
    {
        $merchant = $this->createUser('merchant-slots-create-invalid@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/pickup-slots', $shop->getId()),
            ['starts_at' => (new \DateTimeImmutable('+1 day'))->format(\DateTimeInterface::ATOM)],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(PickupSlot::class)->count(['shop' => $shop]));
    }

    public function testMerchantPickupSlotCreateRejectsStartsAtAfterEndsAt(): void
    {
        $merchant = $this->createUser('merchant-slots-create-dates@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $startsAt = new \DateTimeImmutable('+1 day 11:00');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/pickup-slots', $shop->getId()),
            $this->validMerchantPickupSlotPayload($startsAt, $startsAt->modify('-30 minutes'), 6),
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(PickupSlot::class)->count(['shop' => $shop]));
    }

    public function testMerchantPickupSlotCreateRejectsSlotLongerThanOneHour(): void
    {
        $merchant = $this->createUser('merchant-slots-create-duration@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $startsAt = new \DateTimeImmutable('+1 day 17:00');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/pickup-slots', $shop->getId()),
            $this->validMerchantPickupSlotPayload($startsAt, $startsAt->modify('+6 hours'), 6),
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_MUST_LAST_ONE_HOUR', (string) $response->getContent());
        self::assertSame(0, $this->entityManager->getRepository(PickupSlot::class)->count(['shop' => $shop]));
    }

    public function testMerchantPickupSlotCreateRejectsNonPositiveCapacity(): void
    {
        $merchant = $this->createUser('merchant-slots-create-capacity@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $startsAt = new \DateTimeImmutable('+1 day 10:00');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/pickup-slots', $shop->getId()),
            $this->validMerchantPickupSlotPayload($startsAt, $startsAt->modify('+30 minutes'), 0),
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(PickupSlot::class)->count(['shop' => $shop]));
    }

    public function testMerchantPickupSlotCreateRejectsOverlappingActiveSlot(): void
    {
        $merchant = $this->createUser('merchant-slots-create-overlap@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $timezone = new \DateTimeZone('Africa/Tunis');
        $existingStart = new \DateTimeImmutable('tomorrow 10:00', $timezone);
        $this->createPickupSlot($shop, $existingStart, $existingStart->modify('+1 hour'), 4);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/pickup-slots', $shop->getId()),
            $this->validMerchantPickupSlotPayload($existingStart->modify('+30 minutes'), $existingStart->modify('+90 minutes'), 6),
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_OVERLAPS_EXISTING_SLOT', (string) $response->getContent());
        self::assertSame(1, $this->entityManager->getRepository(PickupSlot::class)->count(['shop' => $shop]));
    }

    public function testMerchantOwnerPatchesPickupSlot(): void
    {
        $merchant = $this->createUser('merchant-slots-patch-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));
        $slot = $this->createPickupSlot($shop, $now->modify('+1 hour'), $now->modify('+2 hours'), 4);
        $newStartsAt = $now->modify('+3 hours');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/pickup-slots/%s', $shop->getId(), $slot->getId()),
            [
                'starts_at' => $newStartsAt->format(\DateTimeInterface::ATOM),
                'ends_at' => $newStartsAt->modify('+1 hour')->format(\DateTimeInterface::ATOM),
                'capacity' => 8,
            ],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $this->entityManager->refresh($slot);
        self::assertSame(8, $slot->getCapacity());
        self::assertSame(
            $newStartsAt->format('Y-m-d H:i'),
            PickupSlotDisplayTime::fromStoredLocalClock($slot->getStartsAt())->format('Y-m-d H:i'),
        );
    }

    public function testMerchantPickupSlotPatchRejectsOtherMerchant(): void
    {
        $owner = $this->createUser('merchant-slots-patch-forbidden-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-slots-patch-forbidden-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);
        $now = new \DateTimeImmutable();
        $slot = $this->createPickupSlot($shop, $now->modify('+1 hour'), $now->modify('+2 hours'), 4);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/pickup-slots/%s', $shop->getId(), $slot->getId()),
            ['capacity' => 8],
            $otherMerchant,
        );

        self::assertSame(403, $response->getStatusCode());
        $this->entityManager->refresh($slot);
        self::assertSame(4, $slot->getCapacity());
    }

    public function testMerchantPickupSlotPatchRejectsCapacityBelowBookedCount(): void
    {
        $merchant = $this->createUser('merchant-slots-patch-booked@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $now = new \DateTimeImmutable();
        $slot = $this->createPickupSlot($shop, $now->modify('+1 hour'), $now->modify('+2 hours'), 3);
        $slot->book();
        $slot->book();
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/pickup-slots/%s', $shop->getId(), $slot->getId()),
            ['capacity' => 1],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        $this->entityManager->refresh($slot);
        self::assertSame(3, $slot->getCapacity());
    }

    public function testMerchantPickupSlotPatchRejectsSlotLongerThanOneHour(): void
    {
        $merchant = $this->createUser('merchant-slots-patch-duration@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));
        $slot = $this->createPickupSlot($shop, $now->modify('+1 hour'), $now->modify('+2 hours'), 3);
        $newStartsAt = $now->modify('+3 hours');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/pickup-slots/%s', $shop->getId(), $slot->getId()),
            [
                'starts_at' => $newStartsAt->format(\DateTimeInterface::ATOM),
                'ends_at' => $newStartsAt->modify('+6 hours')->format(\DateTimeInterface::ATOM),
            ],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_MUST_LAST_ONE_HOUR', (string) $response->getContent());
        $this->entityManager->refresh($slot);
        self::assertSame(
            $now->modify('+1 hour')->format('Y-m-d H:i'),
            PickupSlotDisplayTime::fromStoredLocalClock($slot->getStartsAt())->format('Y-m-d H:i'),
        );
    }

    public function testMerchantPickupSlotPatchAllowsCapacityOnlyUpdateOnLegacyLongSlot(): void
    {
        $merchant = $this->createUser('merchant-slots-patch-legacy-capacity@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));
        $slot = $this->createPickupSlot($shop, $now->modify('+1 hour'), $now->modify('+7 hours'), 3);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/pickup-slots/%s', $shop->getId(), $slot->getId()),
            ['capacity' => 5],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $this->entityManager->refresh($slot);
        self::assertSame(5, $slot->getCapacity());
        self::assertSame(
            $now->modify('+7 hours')->format('Y-m-d H:i'),
            PickupSlotDisplayTime::fromStoredLocalClock($slot->getEndsAt())->format('Y-m-d H:i'),
        );
    }

    public function testMerchantPickupSlotPatchRejectsOverlapWithAnotherActiveSlot(): void
    {
        $merchant = $this->createUser('merchant-slots-patch-overlap@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $timezone = new \DateTimeZone('Africa/Tunis');
        $baseStart = new \DateTimeImmutable('tomorrow 10:00', $timezone);
        $slot = $this->createPickupSlot($shop, $baseStart, $baseStart->modify('+30 minutes'), 3);
        $otherSlot = $this->createPickupSlot($shop, $baseStart->modify('+1 hour'), $baseStart->modify('+2 hours'), 3);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/pickup-slots/%s', $shop->getId(), $slot->getId()),
            [
                'starts_at' => $baseStart->modify('+90 minutes')->format(\DateTimeInterface::ATOM),
                'ends_at' => $baseStart->modify('+150 minutes')->format(\DateTimeInterface::ATOM),
            ],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_OVERLAPS_EXISTING_SLOT', (string) $response->getContent());
        $this->entityManager->refresh($slot);
        $this->entityManager->refresh($otherSlot);
        self::assertSame(
            $baseStart->format('Y-m-d H:i'),
            PickupSlotDisplayTime::fromStoredLocalClock($slot->getStartsAt())->format('Y-m-d H:i'),
        );
        self::assertSame(
            $baseStart->modify('+1 hour')->format('Y-m-d H:i'),
            PickupSlotDisplayTime::fromStoredLocalClock($otherSlot->getStartsAt())->format('Y-m-d H:i'),
        );
    }

    public function testMerchantPickupSlotPatchRejectsUtcManualStartAfterExistingLocalEnd(): void
    {
        $merchant = $this->createUser('merchant-slots-patch-utc-boundary@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $storeId = $shop->getId()->toRfc4122();
        $timezone = new \DateTimeZone('Africa/Tunis');
        $slot = $this->createPickupSlot(
            $shop,
            new \DateTimeImmutable('2030-05-28 17:00:00', $timezone),
            new \DateTimeImmutable('2030-05-28 18:00:00', $timezone),
            3,
        );
        $slotId = $slot->getId()->toRfc4122();

        $this->entityManager->clear();
        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/pickup-slots/%s', $storeId, $slotId),
            ['starts_at' => '2030-05-28T17:30:00+00:00'],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_STARTS_AT_MUST_BE_BEFORE_ENDS_AT', (string) $response->getContent());
    }

    public function testMerchantOwnerCanDeactivatePickupSlot(): void
    {
        $merchant = $this->createUser('merchant-slots-patch-deactivate@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $now = new \DateTimeImmutable();
        $slot = $this->createPickupSlot($shop, $now->modify('+1 hour'), $now->modify('+2 hours'), 3);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/pickup-slots/%s', $shop->getId(), $slot->getId()),
            ['is_active' => false],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $this->entityManager->refresh($slot);
        self::assertFalse($slot->isActive());
    }

    public function testMerchantPickupSlotDeleteSoftDisablesSlot(): void
    {
        $merchant = $this->createUser('merchant-slots-delete-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $now = new \DateTimeImmutable();
        $slot = $this->createPickupSlot($shop, $now->modify('+1 hour'), $now->modify('+2 hours'), 3);

        $response = $this->requestJson('DELETE', \sprintf('/api/merchant/stores/%s/pickup-slots/%s', $shop->getId(), $slot->getId()), user: $merchant);

        self::assertSame(204, $response->getStatusCode());
        $this->entityManager->refresh($slot);
        self::assertFalse($slot->isActive());
        self::assertSame(1, $this->entityManager->getRepository(PickupSlot::class)->count(['shop' => $shop]));
    }

    public function testMerchantPickupSlotDeleteRejectsOtherMerchantClientAndAnonymous(): void
    {
        $owner = $this->createUser('merchant-slots-delete-forbidden-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-slots-delete-forbidden-other@example.test', ['ROLE_MERCHANT']);
        $client = $this->createUser('client-slots-delete@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($owner);
        $now = new \DateTimeImmutable();
        $slot = $this->createPickupSlot($shop, $now->modify('+1 hour'), $now->modify('+2 hours'), 3);
        $path = \sprintf('/api/merchant/stores/%s/pickup-slots/%s', $shop->getId(), $slot->getId());

        $otherMerchantResponse = $this->requestJson('DELETE', $path, user: $otherMerchant);
        $clientResponse = $this->requestJson('DELETE', $path, user: $client);
        $anonymousResponse = $this->requestJson('DELETE', $path);

        self::assertSame(403, $otherMerchantResponse->getStatusCode());
        self::assertSame(403, $clientResponse->getStatusCode());
        self::assertContains($anonymousResponse->getStatusCode(), [401, 403]);
        $storedSlot = $this->entityManager->getRepository(PickupSlot::class)->find($slot->getId());
        self::assertNotNull($storedSlot);
        self::assertTrue($storedSlot->isActive());
    }

    public function testSoftDeletedPickupSlotIsHiddenFromPublicCollection(): void
    {
        $merchant = $this->createUser('merchant-slots-public-hidden@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));
        $slot = $this->createPickupSlot($shop, $now->modify('+1 hour'), $now->modify('+2 hours'), 3);

        $this->requestJson('DELETE', \sprintf('/api/merchant/stores/%s/pickup-slots/%s', $shop->getId(), $slot->getId()), user: $merchant);
        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(0, $payload['items']);
    }

    public function testPickupSlotRoutesExposePublicAndMerchantContracts(): void
    {
        $router = self::getContainer()->get(\Symfony\Component\Routing\RouterInterface::class);
        $pickupSlotRoutes = [];

        foreach ($router->getRouteCollection() as $route) {
            if (str_contains($route->getPath(), 'pickup-slots')) {
                $pickupSlotRoutes[] = implode(' ', $route->getMethods()).' '.$route->getPath();
            }
        }

        sort($pickupSlotRoutes);

        self::assertSame([
            'DELETE /api/merchant/stores/{storeId}/pickup-slots/{slotId}',
            'GET /api/merchant/stores/{storeId}/pickup-slots',
            'GET /api/stores/{storeId}/pickup-slots',
            'PATCH /api/merchant/stores/{storeId}/pickup-slots/{slotId}',
            'POST /api/merchant/stores/{storeId}/pickup-slots',
        ], $pickupSlotRoutes);
    }

    /**
     * @return array<string, mixed>
     */
    private function validMerchantPickupSlotPayload(\DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, int $capacity): array
    {
        return [
            'starts_at' => $startsAt->format(\DateTimeInterface::ATOM),
            'ends_at' => $endsAt->format(\DateTimeInterface::ATOM),
            'capacity' => $capacity,
        ];
    }

    private function createPickupSlot(\App\Entity\Shop $shop, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, int $capacity, bool $active = true): PickupSlot
    {
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt)
            ->setCapacity($capacity)
            ->setActive($active);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }
}
