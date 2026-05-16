<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\PickupSlot;
use App\Entity\PickupSlotRule;
use App\Entity\Shop;

final class MerchantPickupSlotRuleApiTest extends FunctionalApiTestCase
{
    public function testMerchantOwnerCreatesPickupSlotRule(): void
    {
        $merchant = $this->createUser('merchant-slot-rule-create@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/pickup-slot-rules', $shop->getId()),
            $this->validRulePayload(),
            $merchant,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['weekday']);
        self::assertSame('09:00', $payload['start_time']);
        self::assertSame('12:00', $payload['end_time']);
        self::assertSame(5, $payload['capacity']);
        self::assertTrue($payload['is_active']);

        $rules = $this->entityManager->getRepository(PickupSlotRule::class)->findBy(['shop' => $shop]);
        self::assertCount(1, $rules);
        self::assertSame($shop->getId()->toRfc4122(), $rules[0]->getShop()->getId()->toRfc4122());
    }

    public function testGetCollectionReturnsOnlyActiveRulesForOwnedStore(): void
    {
        $merchant = $this->createUser('merchant-slot-rule-list@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-slot-rule-list-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $otherShop = $this->createShop($otherMerchant);
        $activeRule = $this->createRule($shop, 2, '10:00', '11:00', 4);
        $this->createRule($shop, 3, '10:00', '11:00', 4, false);
        $this->createRule($otherShop, 2, '10:00', '11:00', 4);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/pickup-slot-rules', $shop->getId()),
            user: $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertCount(1, $payload['items']);
        self::assertSame($activeRule->getId()->toRfc4122(), $payload['items'][0]['id']);
    }

    public function testMerchantOwnerPatchesPickupSlotRule(): void
    {
        $merchant = $this->createUser('merchant-slot-rule-patch@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $rule = $this->createRule($shop, 1, '09:00', '12:00', 5);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/pickup-slot-rules/%s', $shop->getId(), $rule->getId()),
            [
                'weekday' => 4,
                'start_time' => '14:00',
                'end_time' => '16:00',
                'capacity' => 8,
            ],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(4, $payload['weekday']);
        self::assertSame('14:00', $payload['start_time']);
        self::assertSame('16:00', $payload['end_time']);
        self::assertSame(8, $payload['capacity']);

        $this->entityManager->refresh($rule);
        self::assertSame(4, $rule->getWeekday());
        self::assertSame(8, $rule->getCapacity());
    }

    public function testMerchantOwnerDeletesPickupSlotRuleBySoftDelete(): void
    {
        $merchant = $this->createUser('merchant-slot-rule-delete@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $rule = $this->createRule($shop, 1, '09:00', '12:00', 5);

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/merchant/stores/%s/pickup-slot-rules/%s', $shop->getId(), $rule->getId()),
            user: $merchant,
        );

        self::assertSame(204, $response->getStatusCode());
        $this->entityManager->refresh($rule);
        self::assertFalse($rule->isActive());
        self::assertSame(1, $this->entityManager->getRepository(PickupSlotRule::class)->count(['shop' => $shop]));
    }

    public function testPickupSlotRuleEndpointsRejectAnonymousClientAndOtherMerchant(): void
    {
        $owner = $this->createUser('merchant-slot-rule-security-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-slot-rule-security-other@example.test', ['ROLE_MERCHANT']);
        $client = $this->createUser('client-slot-rule-security@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($owner);
        $path = \sprintf('/api/merchant/stores/%s/pickup-slot-rules', $shop->getId());

        $anonymousResponse = $this->requestJson('GET', $path);
        $clientResponse = $this->requestJson('GET', $path, user: $client);
        $otherMerchantResponse = $this->requestJson('GET', $path, user: $otherMerchant);

        self::assertContains($anonymousResponse->getStatusCode(), [401, 403]);
        self::assertSame(403, $clientResponse->getStatusCode());
        self::assertSame(403, $otherMerchantResponse->getStatusCode());
    }

    public function testPickupSlotRuleCreateRejectsInvalidWeekdayTimeCapacityAndDuplicate(): void
    {
        $merchant = $this->createUser('merchant-slot-rule-validation@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $path = \sprintf('/api/merchant/stores/%s/pickup-slot-rules', $shop->getId());

        $invalidWeekday = $this->requestJson('POST', $path, $this->validRulePayload(['weekday' => 8]), $merchant);
        $invalidTimeRange = $this->requestJson('POST', $path, $this->validRulePayload(['start_time' => '12:00', 'end_time' => '09:00']), $merchant);
        $invalidCapacity = $this->requestJson('POST', $path, $this->validRulePayload(['capacity' => 0]), $merchant);
        $valid = $this->requestJson('POST', $path, $this->validRulePayload(), $merchant);
        $duplicate = $this->requestJson('POST', $path, $this->validRulePayload(), $merchant);

        self::assertSame(422, $invalidWeekday->getStatusCode());
        self::assertSame(422, $invalidTimeRange->getStatusCode());
        self::assertSame(422, $invalidCapacity->getStatusCode());
        self::assertSame(201, $valid->getStatusCode());
        self::assertSame(409, $duplicate->getStatusCode());
    }

    public function testGenerateCreatesFourWeeksOfPickupSlotsFromActiveRules(): void
    {
        $merchant = $this->createUser('merchant-slot-rule-generate@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $weekday = (int) (new \DateTimeImmutable('tomorrow', new \DateTimeZone('Africa/Tunis')))->format('N');
        $this->createRule($shop, $weekday, '09:00', '10:00', 6);
        $this->createRule($shop, $weekday, '11:00', '12:00', 3, false);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/pickup-slot-rules/generate', $shop->getId()),
            [],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(4, $payload['generated_count']);
        self::assertSame(0, $payload['skipped_existing_count']);
        self::assertArrayHasKey('horizon_start', $payload);
        self::assertArrayHasKey('horizon_end', $payload);

        $slots = $this->entityManager->getRepository(PickupSlot::class)->findBy(['shop' => $shop], ['startsAt' => 'ASC']);
        self::assertCount(4, $slots);
        foreach ($slots as $slot) {
            self::assertSame(6, $slot->getCapacity());
            self::assertSame(0, $slot->getBookedCount());
            self::assertTrue($slot->isActive());
            self::assertSame('09:00', $slot->getStartsAt()->setTimezone(new \DateTimeZone('Africa/Tunis'))->format('H:i'));
            self::assertSame('10:00', $slot->getEndsAt()->setTimezone(new \DateTimeZone('Africa/Tunis'))->format('H:i'));
        }
    }

    public function testGenerateIsIdempotentAndDoesNotModifyExistingBookedSlot(): void
    {
        $merchant = $this->createUser('merchant-slot-rule-idempotent@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $timezone = new \DateTimeZone('Africa/Tunis');
        $tomorrow = (new \DateTimeImmutable('tomorrow', $timezone))->setTime(0, 0);
        $weekday = (int) $tomorrow->format('N');
        $this->createRule($shop, $weekday, '09:00', '10:00', 6);

        $existing = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt(new \DateTimeImmutable($tomorrow->format('Y-m-d').' 09:00:00', $timezone))
            ->setEndsAt(new \DateTimeImmutable($tomorrow->format('Y-m-d').' 10:00:00', $timezone))
            ->setCapacity(2)
            ->setActive(true);
        $existing->book();
        $this->entityManager->persist($existing);
        $this->entityManager->flush();

        $path = \sprintf('/api/merchant/stores/%s/pickup-slot-rules/generate', $shop->getId());
        $firstResponse = $this->requestJson('POST', $path, [], $merchant);
        $secondResponse = $this->requestJson('POST', $path, [], $merchant);

        self::assertSame(200, $firstResponse->getStatusCode());
        self::assertSame(200, $secondResponse->getStatusCode());
        $firstPayload = $this->decodeJson($firstResponse);
        $secondPayload = $this->decodeJson($secondResponse);
        self::assertSame(3, $firstPayload['generated_count']);
        self::assertSame(1, $firstPayload['skipped_existing_count']);
        self::assertSame(0, $secondPayload['generated_count']);
        self::assertSame(4, $secondPayload['skipped_existing_count']);

        $storedExisting = $this->entityManager->getRepository(PickupSlot::class)->find($existing->getId());
        self::assertNotNull($storedExisting);
        self::assertSame(2, $storedExisting->getCapacity());
        self::assertSame(1, $storedExisting->getBookedCount());
        self::assertSame(4, $this->entityManager->getRepository(PickupSlot::class)->count(['shop' => $shop]));
    }

    public function testPickupSlotRuleRoutesExposeMerchantContracts(): void
    {
        $router = self::getContainer()->get(\Symfony\Component\Routing\RouterInterface::class);
        $routes = [];

        foreach ($router->getRouteCollection() as $route) {
            if (str_contains($route->getPath(), 'pickup-slot-rules')) {
                $routes[] = implode(' ', $route->getMethods()).' '.$route->getPath();
            }
        }

        sort($routes);

        self::assertSame([
            'DELETE /api/merchant/stores/{storeId}/pickup-slot-rules/{ruleId}',
            'GET /api/merchant/stores/{storeId}/pickup-slot-rules',
            'PATCH /api/merchant/stores/{storeId}/pickup-slot-rules/{ruleId}',
            'POST /api/merchant/stores/{storeId}/pickup-slot-rules',
            'POST /api/merchant/stores/{storeId}/pickup-slot-rules/generate',
        ], $routes);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validRulePayload(array $overrides = []): array
    {
        return array_replace([
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'capacity' => 5,
        ], $overrides);
    }

    private function createRule(
        Shop $shop,
        int $weekday,
        string $startTime,
        string $endTime,
        int $capacity,
        bool $active = true,
    ): PickupSlotRule {
        $rule = (new PickupSlotRule())
            ->setShop($shop)
            ->setWeekday($weekday)
            ->setStartTime(new \DateTimeImmutable('1970-01-01 '.$startTime.':00'))
            ->setEndTime(new \DateTimeImmutable('1970-01-01 '.$endTime.':00'))
            ->setCapacity($capacity)
            ->setActive($active);

        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        return $rule;
    }
}
