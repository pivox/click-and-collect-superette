<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\ExceptionalClosure;
use App\Entity\PickupSlot;
use App\Entity\PickupSlotRule;
use App\Entity\Shop;
use App\Service\PickupSlotRuleGenerator;

final class MerchantExceptionalClosureApiTest extends FunctionalApiTestCase
{
    public function testMerchantOwnerCreatesExceptionalClosure(): void
    {
        $merchant = $this->createUser('merchant-closure-create@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $startsAt = new \DateTimeImmutable('2026-05-20 08:00:00', new \DateTimeZone('Africa/Tunis'));
        $endsAt = $startsAt->modify('+10 hours');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/exceptional-closures', $shop->getId()),
            $this->validClosurePayload($startsAt, $endsAt, ' Fermeture inventaire '),
            $merchant,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertIsString($payload['id']);
        self::assertSame($startsAt->format(\DateTimeInterface::ATOM), $payload['starts_at']);
        self::assertSame($endsAt->format(\DateTimeInterface::ATOM), $payload['ends_at']);
        self::assertSame('Fermeture inventaire', $payload['reason']);
        self::assertTrue($payload['is_active']);

        $closures = $this->entityManager->getRepository(ExceptionalClosure::class)->findBy(['shop' => $shop]);
        self::assertCount(1, $closures);
        self::assertSame('Fermeture inventaire', $closures[0]->getReason());
    }

    public function testCollectionReturnsOnlyClosuresForMerchantShop(): void
    {
        $merchant = $this->createUser('merchant-closure-list@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-closure-list-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $otherShop = $this->createShop($otherMerchant);
        $this->createClosure($shop, '2026-05-20 08:00:00', '2026-05-20 18:00:00', 'Inventaire');
        $this->createClosure($otherShop, '2026-05-21 08:00:00', '2026-05-21 18:00:00', 'Autre shop');

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/exceptional-closures', $shop->getId()), user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertCount(1, $payload['items']);
        self::assertSame('Inventaire', $payload['items'][0]['reason']);
    }

    public function testMerchantOwnerPatchesExceptionalClosure(): void
    {
        $merchant = $this->createUser('merchant-closure-patch@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $closure = $this->createClosure($shop, '2026-05-20 08:00:00', '2026-05-20 18:00:00', 'Inventaire');
        $startsAt = new \DateTimeImmutable('2026-05-21 09:00:00', new \DateTimeZone('Africa/Tunis'));
        $endsAt = $startsAt->modify('+5 hours');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/exceptional-closures/%s', $shop->getId(), $closure->getId()),
            $this->validClosurePayload($startsAt, $endsAt, 'Maintenance'),
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($startsAt->format(\DateTimeInterface::ATOM), $payload['starts_at']);
        self::assertSame($endsAt->format(\DateTimeInterface::ATOM), $payload['ends_at']);
        self::assertSame('Maintenance', $payload['reason']);

        $this->entityManager->refresh($closure);
        self::assertSame('Maintenance', $closure->getReason());
    }

    public function testMerchantOwnerDeletesExceptionalClosureBySoftDelete(): void
    {
        $merchant = $this->createUser('merchant-closure-delete@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $closure = $this->createClosure($shop, '2026-05-20 08:00:00', '2026-05-20 18:00:00', 'Inventaire');

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/merchant/stores/%s/exceptional-closures/%s', $shop->getId(), $closure->getId()),
            user: $merchant,
        );

        self::assertSame(204, $response->getStatusCode());
        $this->entityManager->refresh($closure);
        self::assertFalse($closure->isActive());
        self::assertSame(1, $this->entityManager->getRepository(ExceptionalClosure::class)->count(['shop' => $shop]));
    }

    public function testExceptionalClosureEndpointsRejectAnonymousClientAndOtherMerchant(): void
    {
        $merchant = $this->createUser('merchant-closure-forbidden-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-closure-forbidden-other@example.test', ['ROLE_MERCHANT']);
        $client = $this->createUser('client-closure-forbidden@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($merchant);
        $closure = $this->createClosure($shop, '2026-05-20 08:00:00', '2026-05-20 18:00:00', 'Inventaire');
        $collectionPath = \sprintf('/api/merchant/stores/%s/exceptional-closures', $shop->getId());
        $itemPath = \sprintf('/api/merchant/stores/%s/exceptional-closures/%s', $shop->getId(), $closure->getId());

        $anonymousResponse = $this->requestJson('GET', $collectionPath);
        $clientResponse = $this->requestJson('GET', $collectionPath, user: $client);
        $otherMerchantResponse = $this->requestJson('PATCH', $itemPath, ['reason' => 'Forbidden'], $otherMerchant);

        self::assertContains($anonymousResponse->getStatusCode(), [401, 403]);
        self::assertSame(403, $clientResponse->getStatusCode());
        self::assertSame(403, $otherMerchantResponse->getStatusCode());

        $storedClosure = $this->entityManager->getRepository(ExceptionalClosure::class)->find($closure->getId());
        self::assertNotNull($storedClosure);
        self::assertSame('Inventaire', $storedClosure->getReason());
    }

    public function testExceptionalClosureCreateRejectsInvalidRangeAndLongReason(): void
    {
        $merchant = $this->createUser('merchant-closure-invalid@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $startsAt = new \DateTimeImmutable('2026-05-20 18:00:00', new \DateTimeZone('Africa/Tunis'));
        $endsAt = $startsAt->modify('-1 hour');
        $path = \sprintf('/api/merchant/stores/%s/exceptional-closures', $shop->getId());

        $rangeResponse = $this->requestJson('POST', $path, $this->validClosurePayload($startsAt, $endsAt), $merchant);
        $reasonResponse = $this->requestJson('POST', $path, $this->validClosurePayload($endsAt, $startsAt, str_repeat('a', 256)), $merchant);

        self::assertSame(422, $rangeResponse->getStatusCode());
        self::assertSame(422, $reasonResponse->getStatusCode());
    }

    public function testCreateClosureDisablesActiveUnbookedPickupSlotsInRange(): void
    {
        $merchant = $this->createUser('merchant-closure-disable-slots@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $timezone = new \DateTimeZone('Africa/Tunis');
        $inside = $this->createPickupSlot(
            $shop,
            new \DateTimeImmutable('2026-05-20 09:00:00', $timezone),
            new \DateTimeImmutable('2026-05-20 10:00:00', $timezone),
            4,
        );
        $outside = $this->createPickupSlot(
            $shop,
            new \DateTimeImmutable('2026-05-21 09:00:00', $timezone),
            new \DateTimeImmutable('2026-05-21 10:00:00', $timezone),
            4,
        );

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/exceptional-closures', $shop->getId()),
            $this->validClosurePayload(new \DateTimeImmutable('2026-05-20 08:00:00', $timezone), new \DateTimeImmutable('2026-05-20 18:00:00', $timezone)),
            $merchant,
        );

        self::assertSame(201, $response->getStatusCode());
        $this->entityManager->refresh($inside);
        $this->entityManager->refresh($outside);
        self::assertFalse($inside->isActive());
        self::assertTrue($outside->isActive());
    }

    public function testCreateClosureRejectsBookedPickupSlotInRange(): void
    {
        $merchant = $this->createUser('merchant-closure-booked-create@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $timezone = new \DateTimeZone('Africa/Tunis');
        $slot = $this->createPickupSlot(
            $shop,
            new \DateTimeImmutable('2026-05-20 09:00:00', $timezone),
            new \DateTimeImmutable('2026-05-20 10:00:00', $timezone),
            4,
        );
        $slot->book();
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/exceptional-closures', $shop->getId()),
            $this->validClosurePayload(new \DateTimeImmutable('2026-05-20 08:00:00', $timezone), new \DateTimeImmutable('2026-05-20 18:00:00', $timezone)),
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('EXCEPTIONAL_CLOSURE_HAS_BOOKED_SLOTS', (string) $response->getContent());
        $this->entityManager->refresh($slot);
        self::assertTrue($slot->isActive());
        self::assertSame(0, $this->entityManager->getRepository(ExceptionalClosure::class)->count(['shop' => $shop]));
    }

    public function testPatchClosureRejectsBookedPickupSlotInNewRange(): void
    {
        $merchant = $this->createUser('merchant-closure-booked-patch@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $timezone = new \DateTimeZone('Africa/Tunis');
        $closure = $this->createClosure($shop, '2026-05-20 08:00:00', '2026-05-20 18:00:00', 'Inventaire');
        $slot = $this->createPickupSlot(
            $shop,
            new \DateTimeImmutable('2026-05-21 09:00:00', $timezone),
            new \DateTimeImmutable('2026-05-21 10:00:00', $timezone),
            4,
        );
        $slot->book();
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/exceptional-closures/%s', $shop->getId(), $closure->getId()),
            $this->validClosurePayload(new \DateTimeImmutable('2026-05-21 08:00:00', $timezone), new \DateTimeImmutable('2026-05-21 18:00:00', $timezone), 'Maintenance'),
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        $storedClosure = $this->entityManager->getRepository(ExceptionalClosure::class)->find($closure->getId());
        self::assertNotNull($storedClosure);
        self::assertSame('Inventaire', $storedClosure->getReason());
        self::assertSame('2026-05-20', $storedClosure->getStartsAt()->setTimezone($timezone)->format('Y-m-d'));
    }

    public function testPatchClosureRejectsRangeInvalidAgainstExistingStartsAt(): void
    {
        $merchant = $this->createUser('merchant-closure-patch-partial-invalid@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $closure = $this->createClosure($shop, '2026-05-20 08:00:00', '2026-05-20 18:00:00', 'Inventaire');
        $timezone = new \DateTimeZone('Africa/Tunis');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/exceptional-closures/%s', $shop->getId(), $closure->getId()),
            ['ends_at' => (new \DateTimeImmutable('2026-05-20 07:00:00', $timezone))->format(\DateTimeInterface::ATOM)],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('EXCEPTIONAL_CLOSURE_STARTS_AT_MUST_BE_BEFORE_ENDS_AT', (string) $response->getContent());
    }

    public function testMerchantCannotCreateOrReactivatePickupSlotInsideActiveClosure(): void
    {
        $merchant = $this->createUser('merchant-closure-slot-create-blocked@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $timezone = new \DateTimeZone('Africa/Tunis');
        $this->createClosure($shop, '2026-05-20 08:00:00', '2026-05-20 18:00:00', 'Inventaire');
        $startsAt = new \DateTimeImmutable('2026-05-20 09:00:00', $timezone);
        $endsAt = new \DateTimeImmutable('2026-05-20 10:00:00', $timezone);

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/pickup-slots', $shop->getId()),
            [
                'starts_at' => $startsAt->format(\DateTimeInterface::ATOM),
                'ends_at' => $endsAt->format(\DateTimeInterface::ATOM),
                'capacity' => 4,
            ],
            $merchant,
        );

        self::assertSame(422, $createResponse->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_OVERLAPS_EXCEPTIONAL_CLOSURE', (string) $createResponse->getContent());

        $inactiveSlot = $this->createPickupSlot($shop, $startsAt, $endsAt, 4);
        $inactiveSlot->setActive(false);
        $this->entityManager->flush();

        $patchResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/pickup-slots/%s', $shop->getId(), $inactiveSlot->getId()),
            ['is_active' => true],
            $merchant,
        );

        self::assertSame(422, $patchResponse->getStatusCode());
        self::assertStringContainsString('PICKUP_SLOT_OVERLAPS_EXCEPTIONAL_CLOSURE', (string) $patchResponse->getContent());
    }

    public function testPublicPickupSlotCollectionHidesSlotsInsideActiveClosure(): void
    {
        $merchant = $this->createUser('merchant-closure-public-slots@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $timezone = new \DateTimeZone('Africa/Tunis');
        $tomorrow = new \DateTimeImmutable('tomorrow 09:00:00', $timezone);
        $dayAfter = new \DateTimeImmutable('tomorrow +1 day 09:00:00', $timezone);
        $closedSlot = $this->createPickupSlot(
            $shop,
            $tomorrow,
            $tomorrow->modify('+1 hour'),
            4,
        );
        $openSlot = $this->createPickupSlot(
            $shop,
            $dayAfter,
            $dayAfter->modify('+1 hour'),
            4,
        );
        $this->createClosure(
            $shop,
            $tomorrow->modify('-1 hour')->format('Y-m-d H:i:s'),
            $tomorrow->modify('+2 hours')->format('Y-m-d H:i:s'),
            'Inventaire',
        );

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/pickup-slots', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertSame($openSlot->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertNotSame($closedSlot->getId()->toRfc4122(), $payload['items'][0]['id']);
    }

    public function testInactiveClosureDoesNotBlockGeneration(): void
    {
        $merchant = $this->createUser('merchant-closure-inactive-generation@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $timezone = new \DateTimeZone('Africa/Tunis');
        $now = new \DateTimeImmutable('2026-05-18 08:00:00', $timezone);
        $this->createRule($shop, (int) $now->format('N'), '09:00', '10:00', 6);
        $this->createClosure($shop, '2026-05-18 08:00:00', '2026-05-18 18:00:00', 'Inactive', false);

        $generator = self::getContainer()->get(PickupSlotRuleGenerator::class);
        $result = $generator->generateForShop($shop, $now);

        self::assertSame(4, $result->generatedCount);
        self::assertSame(0, $result->skippedClosureCount);
    }

    public function testGenerationSkipsActiveClosureAndStaysIdempotent(): void
    {
        $merchant = $this->createUser('merchant-closure-generation@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $timezone = new \DateTimeZone('Africa/Tunis');
        $now = new \DateTimeImmutable('now', $timezone);
        $closedDate = $now->modify('+1 day')->setTime(8, 0);
        $this->createRule($shop, (int) $closedDate->format('N'), '09:00', '10:00', 6);
        $this->createClosure(
            $shop,
            $closedDate->format('Y-m-d 08:00:00'),
            $closedDate->format('Y-m-d 18:00:00'),
            'Inventaire',
        );

        $generator = self::getContainer()->get(PickupSlotRuleGenerator::class);
        $firstResult = $generator->generateForShop($shop, $now);
        $secondResult = $generator->generateForShop($shop, $now);

        self::assertSame(3, $firstResult->generatedCount);
        self::assertSame(1, $firstResult->skippedClosureCount);
        self::assertSame(0, $firstResult->skippedExistingCount);
        self::assertSame(0, $secondResult->generatedCount);
        self::assertSame(1, $secondResult->skippedClosureCount);
        self::assertSame(3, $secondResult->skippedExistingCount);
        self::assertSame(3, $this->entityManager->getRepository(PickupSlot::class)->count(['shop' => $shop]));
    }

    public function testDeletingClosureDoesNotReactivateDisabledPickupSlots(): void
    {
        $merchant = $this->createUser('merchant-closure-no-reactivate@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $timezone = new \DateTimeZone('Africa/Tunis');
        $slot = $this->createPickupSlot(
            $shop,
            new \DateTimeImmutable('2026-05-20 09:00:00', $timezone),
            new \DateTimeImmutable('2026-05-20 10:00:00', $timezone),
            4,
        );
        $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/exceptional-closures', $shop->getId()),
            $this->validClosurePayload(new \DateTimeImmutable('2026-05-20 08:00:00', $timezone), new \DateTimeImmutable('2026-05-20 18:00:00', $timezone)),
            $merchant,
        );
        $closure = $this->entityManager->getRepository(ExceptionalClosure::class)->findOneBy(['shop' => $shop]);
        self::assertNotNull($closure);

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/merchant/stores/%s/exceptional-closures/%s', $shop->getId(), $closure->getId()),
            user: $merchant,
        );

        self::assertSame(204, $response->getStatusCode());
        $storedSlot = $this->entityManager->getRepository(PickupSlot::class)->find($slot->getId());
        self::assertNotNull($storedSlot);
        self::assertFalse($storedSlot->isActive());
    }

    public function testExceptionalClosureRoutesExposeMerchantContracts(): void
    {
        $router = self::getContainer()->get(\Symfony\Component\Routing\RouterInterface::class);
        $routes = [];

        foreach ($router->getRouteCollection() as $route) {
            if (str_contains($route->getPath(), 'exceptional-closures')) {
                $routes[] = implode(' ', $route->getMethods()).' '.$route->getPath();
            }
        }

        sort($routes);

        self::assertSame([
            'DELETE /api/merchant/stores/{storeId}/exceptional-closures/{closureId}',
            'GET /api/merchant/stores/{storeId}/exceptional-closures',
            'PATCH /api/merchant/stores/{storeId}/exceptional-closures/{closureId}',
            'POST /api/merchant/stores/{storeId}/exceptional-closures',
        ], $routes);
    }

    /**
     * @return array<string, mixed>
     */
    private function validClosurePayload(\DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, ?string $reason = 'Inventaire'): array
    {
        return [
            'starts_at' => $startsAt->format(\DateTimeInterface::ATOM),
            'ends_at' => $endsAt->format(\DateTimeInterface::ATOM),
            'reason' => $reason,
        ];
    }

    private function createClosure(
        Shop $shop,
        string $startsAt,
        string $endsAt,
        ?string $reason,
        bool $active = true,
    ): ExceptionalClosure {
        $timezone = new \DateTimeZone('Africa/Tunis');
        $closure = (new ExceptionalClosure())
            ->setShop($shop)
            ->setStartsAt(new \DateTimeImmutable($startsAt, $timezone))
            ->setEndsAt(new \DateTimeImmutable($endsAt, $timezone))
            ->setReason($reason)
            ->setActive($active);

        $this->entityManager->persist($closure);
        $this->entityManager->flush();

        return $closure;
    }

    private function createPickupSlot(Shop $shop, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, int $capacity): PickupSlot
    {
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt)
            ->setCapacity($capacity)
            ->setActive(true);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }

    private function createRule(Shop $shop, int $weekday, string $startTime, string $endTime, int $capacity): PickupSlotRule
    {
        $rule = (new PickupSlotRule())
            ->setShop($shop)
            ->setWeekday($weekday)
            ->setStartTime(new \DateTimeImmutable('1970-01-01 '.$startTime.':00'))
            ->setEndTime(new \DateTimeImmutable('1970-01-01 '.$endTime.':00'))
            ->setCapacity($capacity)
            ->setActive(true);

        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        return $rule;
    }
}
