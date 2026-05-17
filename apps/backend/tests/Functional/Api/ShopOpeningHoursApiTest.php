<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Shop;
use Symfony\Component\Uid\Uuid;

final class ShopOpeningHoursApiTest extends FunctionalApiTestCase
{
    public function testPublicOpeningHoursReturnsNullWhenNotConfigured(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/opening-hours', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertNull($payload['opening_hours']);
    }

    public function testMerchantOwnerCanPatchAndReadOpeningHours(): void
    {
        $merchant = $this->createUser('merchant-opening-hours@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/opening-hours', $shop->getId()),
            ['opening_hours' => $this->validOpeningHours()],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame('Africa/Tunis', $payload['opening_hours']['timezone']);
        self::assertSame([['start' => '08:00', 'end' => '12:00'], ['start' => '15:00', 'end' => '20:00']], $payload['opening_hours']['weekly']['1']);
        self::assertSame([], $payload['opening_hours']['weekly']['3']);

        $this->entityManager->refresh($shop);
        self::assertSame($payload['opening_hours'], $shop->getOpeningHours());

        $merchantGetResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/opening-hours', $shop->getId()), user: $merchant);
        self::assertSame(200, $merchantGetResponse->getStatusCode());
        self::assertSame($payload, $this->decodeJson($merchantGetResponse));
    }

    public function testPublicOpeningHoursReturnsConfiguredOpeningHours(): void
    {
        $merchant = $this->createUser('merchant-opening-hours-public@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $shop->setOpeningHours($this->validOpeningHours());
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/opening-hours', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame($this->validOpeningHours(), $payload['opening_hours']);
    }

    public function testPatchReplacesWholeOpeningHoursStructure(): void
    {
        $merchant = $this->createUser('merchant-opening-hours-replace@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $shop->setOpeningHours($this->validOpeningHours());
        $this->entityManager->flush();
        $replacement = $this->validOpeningHours([
            '1' => [],
            '2' => [['start' => '09:00', 'end' => '18:00']],
        ]);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/opening-hours', $shop->getId()),
            ['opening_hours' => $replacement],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame([], $payload['opening_hours']['weekly']['1']);
        self::assertSame([['start' => '09:00', 'end' => '18:00']], $payload['opening_hours']['weekly']['2']);

        $this->entityManager->refresh($shop);
        self::assertSame($replacement, $shop->getOpeningHours());
    }

    public function testMerchantOpeningHoursSecurity(): void
    {
        $merchant = $this->createUser('merchant-opening-hours-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-opening-hours-other@example.test', ['ROLE_MERCHANT']);
        $client = $this->createUser('client-opening-hours@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($merchant);
        $path = \sprintf('/api/merchant/stores/%s/opening-hours', $shop->getId());

        $anonymousGetResponse = $this->requestJson('GET', $path);
        $clientGetResponse = $this->requestJson('GET', $path, user: $client);
        $otherMerchantGetResponse = $this->requestJson('GET', $path, user: $otherMerchant);
        $otherMerchantPatchResponse = $this->requestJson('PATCH', $path, ['opening_hours' => $this->validOpeningHours()], $otherMerchant);

        self::assertContains($anonymousGetResponse->getStatusCode(), [401, 403]);
        self::assertSame(403, $clientGetResponse->getStatusCode());
        self::assertSame(403, $otherMerchantGetResponse->getStatusCode());
        self::assertSame(403, $otherMerchantPatchResponse->getStatusCode());
        self::assertNull($shop->getOpeningHours());
    }

    public function testUnknownStoreReturnsNotFound(): void
    {
        $merchant = $this->createUser('merchant-opening-hours-unknown@example.test', ['ROLE_MERCHANT']);
        $storeId = Uuid::v4();

        $publicResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/opening-hours', $storeId));
        $merchantResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/opening-hours', $storeId), user: $merchant);

        self::assertSame(404, $publicResponse->getStatusCode());
        self::assertSame(404, $merchantResponse->getStatusCode());
    }

    public function testPublicOpeningHoursReturnsNotFoundForInactiveStore(): void
    {
        $merchant = $this->createUser('merchant-opening-hours-inactive@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant, active: false);
        $shop->setOpeningHours($this->validOpeningHours());
        $this->entityManager->flush();

        $publicResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/opening-hours', $shop->getId()));
        $merchantResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/opening-hours', $shop->getId()), user: $merchant);

        self::assertSame(404, $publicResponse->getStatusCode());
        self::assertSame(200, $merchantResponse->getStatusCode());
    }

    public function testOpeningHoursValidationRejectsInvalidPayloads(): void
    {
        $merchant = $this->createUser('merchant-opening-hours-validation@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $path = \sprintf('/api/merchant/stores/%s/opening-hours', $shop->getId());

        $cases = [
            'missing timezone' => $this->withoutKey($this->validOpeningHours(), 'timezone'),
            'invalid timezone' => $this->validOpeningHours(timezone: 'Europe/Paris'),
            'missing weekly' => $this->withoutKey($this->validOpeningHours(), 'weekly'),
            'incomplete weekly' => $this->validOpeningHours(['7' => null]),
            'invalid day key' => $this->validOpeningHours(['8' => []]),
            'invalid time format' => $this->validOpeningHours(['1' => [['start' => '8:00', 'end' => '12:00']]]),
            'start after end' => $this->validOpeningHours(['1' => [['start' => '12:00', 'end' => '12:00']]]),
            'overlap' => $this->validOpeningHours(['1' => [['start' => '08:00', 'end' => '12:00'], ['start' => '11:00', 'end' => '14:00']]]),
            'too many ranges' => $this->validOpeningHours(['1' => [
                ['start' => '08:00', 'end' => '09:00'],
                ['start' => '10:00', 'end' => '11:00'],
                ['start' => '12:00', 'end' => '13:00'],
            ]]),
        ];

        foreach ($cases as $label => $openingHours) {
            $response = $this->requestJson('PATCH', $path, ['opening_hours' => $openingHours], $merchant);
            self::assertSame(422, $response->getStatusCode(), $label.' should be rejected');
        }

        $storedShop = $this->entityManager->getRepository(Shop::class)->find($shop->getId());
        self::assertNotNull($storedShop);
        self::assertNull($storedShop->getOpeningHours());
    }

    public function testAdjacentRangesAreAccepted(): void
    {
        $merchant = $this->createUser('merchant-opening-hours-adjacent@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $openingHours = $this->validOpeningHours([
            '1' => [
                ['start' => '08:00', 'end' => '12:00'],
                ['start' => '12:00', 'end' => '16:00'],
            ],
        ]);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/opening-hours', $shop->getId()),
            ['opening_hours' => $openingHours],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($openingHours, $this->decodeJson($response)['opening_hours']);
    }

    public function testPatchSortsRangesBeforePersisting(): void
    {
        $merchant = $this->createUser('merchant-opening-hours-sort@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $openingHours = $this->validOpeningHours([
            '1' => [
                ['start' => '15:00', 'end' => '20:00'],
                ['start' => '08:00', 'end' => '12:00'],
            ],
        ]);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/opening-hours', $shop->getId()),
            ['opening_hours' => $openingHours],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            ['start' => '08:00', 'end' => '12:00'],
            ['start' => '15:00', 'end' => '20:00'],
        ], $this->decodeJson($response)['opening_hours']['weekly']['1']);
    }

    public function testOpeningHoursRoutesAreRegistered(): void
    {
        $router = self::getContainer()->get('router');
        $routes = [];

        foreach ($router->getRouteCollection() as $route) {
            if (str_contains($route->getPath(), 'opening-hours')) {
                foreach ($route->getMethods() as $method) {
                    $routes[] = $method.' '.$route->getPath();
                }
            }
        }

        sort($routes);
        self::assertSame([
            'GET /api/merchant/stores/{storeId}/opening-hours',
            'GET /api/stores/{storeId}/opening-hours',
            'PATCH /api/merchant/stores/{storeId}/opening-hours',
        ], $routes);
    }

    /**
     * @param array<string, list<array{start: string, end: string}>|null> $weeklyOverrides
     *
     * @return array{timezone: string, weekly: array<string, list<array{start: string, end: string}>>}
     */
    private function validOpeningHours(array $weeklyOverrides = [], string $timezone = 'Africa/Tunis'): array
    {
        $weekly = [
            '1' => [['start' => '08:00', 'end' => '12:00'], ['start' => '15:00', 'end' => '20:00']],
            '2' => [['start' => '08:00', 'end' => '20:00']],
            '3' => [],
            '4' => [],
            '5' => [],
            '6' => [],
            '7' => [],
        ];

        foreach ($weeklyOverrides as $day => $ranges) {
            if (null === $ranges) {
                unset($weekly[$day]);
                continue;
            }

            $weekly[$day] = $ranges;
        }

        return [
            'timezone' => $timezone,
            'weekly' => $weekly,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function withoutKey(array $payload, string $key): array
    {
        unset($payload[$key]);

        return $payload;
    }
}
