<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\ShopOrderingPolicy;
use PHPUnit\Framework\Attributes\DataProvider;

final class ShopOrderingPolicyApiTest extends FunctionalApiTestCase
{
    public function testOwnerReadsDefaultPolicyWhenNoRowExists(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', $this->policyUrl($shop->getId()->toRfc4122()), user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame(0, $payload['minimum_pickup_lead_time_minutes']);
        // No persisted policy yet: nullable updated_at is excluded from JSON.
        self::assertArrayNotHasKey('updated_at', $payload);

        self::assertNull(
            $this->entityManager->getRepository(ShopOrderingPolicy::class)->findOneBy(['shop' => $shop]),
            'Reading the default policy must not create a row.',
        );
    }

    public function testOwnerCanSetLeadTime(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'PATCH',
            $this->policyUrl($shop->getId()->toRfc4122()),
            ['minimum_pickup_lead_time_minutes' => 720],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame(720, $payload['minimum_pickup_lead_time_minutes']);
        self::assertArrayHasKey('updated_at', $payload);

        $this->entityManager->clear();
        $persisted = $this->entityManager->getRepository(ShopOrderingPolicy::class)->findOneBy(['shop' => $shop]);
        self::assertInstanceOf(ShopOrderingPolicy::class, $persisted);
        self::assertSame(720, $persisted->getMinimumPickupLeadTimeMinutes());
    }

    public function testGetReflectsPersistedPolicy(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $patch = $this->requestJson(
            'PATCH',
            $this->policyUrl($shop->getId()->toRfc4122()),
            ['minimum_pickup_lead_time_minutes' => 1440],
            $merchant,
        );
        self::assertSame(200, $patch->getStatusCode());

        $get = $this->requestJson('GET', $this->policyUrl($shop->getId()->toRfc4122()), user: $merchant);
        self::assertSame(200, $get->getStatusCode());
        $payload = $this->decodeJson($get);
        self::assertSame(1440, $payload['minimum_pickup_lead_time_minutes']);
        self::assertArrayHasKey('updated_at', $payload);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideValidBoundaryValues(): iterable
    {
        yield 'zero' => [0];
        yield 'one minute' => [1];
        yield 'twelve hours' => [720];
        yield 'seven days' => [10080];
    }

    #[DataProvider('provideValidBoundaryValues')]
    public function testPatchAcceptsBoundaryValue(int $minutes): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'PATCH',
            $this->policyUrl($shop->getId()->toRfc4122()),
            ['minimum_pickup_lead_time_minutes' => $minutes],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($minutes, $this->decodeJson($response)['minimum_pickup_lead_time_minutes']);
    }

    public function testPatchIsIdempotentAndKeepsSingleRow(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $url = $this->policyUrl($shop->getId()->toRfc4122());

        $first = $this->requestJson('PATCH', $url, ['minimum_pickup_lead_time_minutes' => 240], $merchant);
        self::assertSame(200, $first->getStatusCode());

        $second = $this->requestJson('PATCH', $url, ['minimum_pickup_lead_time_minutes' => 240], $merchant);
        self::assertSame(200, $second->getStatusCode());
        self::assertSame(240, $this->decodeJson($second)['minimum_pickup_lead_time_minutes']);

        $third = $this->requestJson('PATCH', $url, ['minimum_pickup_lead_time_minutes' => 480], $merchant);
        self::assertSame(200, $third->getStatusCode());

        $this->entityManager->clear();
        $rows = $this->entityManager->getRepository(ShopOrderingPolicy::class)->findBy(['shop' => $shop]);
        self::assertCount(1, $rows, 'Repeated PATCHes must never create a second policy row.');
        self::assertSame(480, $rows[0]->getMinimumPickupLeadTimeMinutes());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideInvalidLeadTimeValues(): iterable
    {
        yield 'negative' => [-1];
        yield 'above maximum' => [10081];
        yield 'decimal' => [7.5];
        yield 'numeric string' => ['720'];
        yield 'boolean' => [true];
        yield 'null' => [null];
    }

    #[DataProvider('provideInvalidLeadTimeValues')]
    public function testPatchRejectsInvalidLeadTime(mixed $value): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'PATCH',
            $this->policyUrl($shop->getId()->toRfc4122()),
            ['minimum_pickup_lead_time_minutes' => $value],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('SHOP_ORDERING_POLICY_INVALID_LEAD_TIME', $this->decodeJson($response)['detail']);
    }

    public function testPatchRejectsMissingField(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('PATCH', $this->policyUrl($shop->getId()->toRfc4122()), [], $merchant);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('SHOP_ORDERING_POLICY_INVALID_LEAD_TIME', $this->decodeJson($response)['detail']);
    }

    public function testPatchRejectsUnknownField(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'PATCH',
            $this->policyUrl($shop->getId()->toRfc4122()),
            ['minimum_pickup_lead_time_minutes' => 60, 'maximum_booking_horizon_minutes' => 120],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('SHOP_ORDERING_POLICY_UNKNOWN_FIELD', $this->decodeJson($response)['detail']);

        self::assertNull(
            $this->entityManager->getRepository(ShopOrderingPolicy::class)->findOneBy(['shop' => $shop]),
            'A rejected PATCH must not persist a policy row.',
        );
    }

    public function testNonOwnerMerchantIsDenied(): void
    {
        $owner = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $other = $this->createUser('merchant-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);

        $get = $this->requestJson('GET', $this->policyUrl($shop->getId()->toRfc4122()), user: $other);
        self::assertSame(403, $get->getStatusCode());

        $patch = $this->requestJson(
            'PATCH',
            $this->policyUrl($shop->getId()->toRfc4122()),
            ['minimum_pickup_lead_time_minutes' => 60],
            $other,
        );
        self::assertSame(403, $patch->getStatusCode());
    }

    public function testCustomerIsDenied(): void
    {
        $owner = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($owner);

        $response = $this->requestJson(
            'PATCH',
            $this->policyUrl($shop->getId()->toRfc4122()),
            ['minimum_pickup_lead_time_minutes' => 60],
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAnonymousIsDenied(): void
    {
        $owner = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);

        $response = $this->requestJson('GET', $this->policyUrl($shop->getId()->toRfc4122()));

        self::assertContains($response->getStatusCode(), [401, 403]);
    }

    public function testSuspendedOwnerIsDenied(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $merchant->setActive(false);
        $this->entityManager->flush();

        $patch = $this->requestJson(
            'PATCH',
            $this->policyUrl($shop->getId()->toRfc4122()),
            ['minimum_pickup_lead_time_minutes' => 60],
            $merchant,
        );
        self::assertSame(403, $patch->getStatusCode());

        $get = $this->requestJson('GET', $this->policyUrl($shop->getId()->toRfc4122()), user: $merchant);
        self::assertSame(403, $get->getStatusCode());
    }

    public function testUnknownStoreReturns404(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);

        // Valid non-existing UUID v4 (nil UUID can trip validation instead of 404).
        $response = $this->requestJson(
            'GET',
            $this->policyUrl('550e8400-e29b-41d4-a716-446655440000'),
            user: $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testMalformedStoreIdReturns404(): void
    {
        $merchant = $this->createUser('merchant-owner@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('GET', $this->policyUrl('not-a-uuid'), user: $merchant);

        self::assertSame(404, $response->getStatusCode());
    }

    private function policyUrl(string $storeId): string
    {
        return \sprintf('/api/merchant/stores/%s/ordering-policy', $storeId);
    }
}
