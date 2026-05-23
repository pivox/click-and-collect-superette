<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

final class MerchantMeApiTest extends FunctionalApiTestCase
{
    public function testMerchantCanReadTheirCurrentActiveStoreContext(): void
    {
        $merchant = $this->createUser('merchant-me@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $shop->setName('Supérette Ezzahra');
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/merchant/me', null, $merchant);

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['user_id']);
        self::assertSame('merchant-me@example.test', $payload['email']);
        self::assertSame(['ROLE_MERCHANT'], $payload['roles']);
        self::assertSame(
            [
                'id' => $shop->getId()->toRfc4122(),
                'name' => 'Supérette Ezzahra',
                'active' => true,
            ],
            $payload['store'],
        );
        self::assertFalse($payload['onboarding_completed']);
        self::assertArrayNotHasKey('steps', $payload);
    }

    public function testMerchantMeIsForbiddenForNonMerchant(): void
    {
        $customer = $this->createUser('merchant-me-customer@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('GET', '/api/merchant/me', null, $customer);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testMerchantMeIsForbiddenForSuspendedMerchant(): void
    {
        $merchant = $this->createUser('merchant-me-suspended@example.test', ['ROLE_MERCHANT']);
        $merchant->setActive(false);
        $this->createShop($merchant);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/merchant/me', null, $merchant);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testMerchantMeRequiresAuthentication(): void
    {
        $response = $this->requestJson('GET', '/api/merchant/me');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testMerchantMeReturns404WhenMerchantHasNoActiveStore(): void
    {
        $merchant = $this->createUser('merchant-me-no-active-store@example.test', ['ROLE_MERCHANT']);
        $this->createShop($merchant, active: false);

        $response = $this->requestJson('GET', '/api/merchant/me', null, $merchant);

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('MERCHANT_ACTIVE_STORE_NOT_FOUND', (string) $response->getContent());
    }

    public function testMerchantMeReturns409WhenMerchantHasMultipleActiveStores(): void
    {
        $merchant = $this->createUser('merchant-me-multiple-stores@example.test', ['ROLE_MERCHANT']);
        $this->createShop($merchant, active: true);
        $this->createShop($merchant, active: true);

        $response = $this->requestJson('GET', '/api/merchant/me', null, $merchant);

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('MERCHANT_MULTIPLE_ACTIVE_STORES', (string) $response->getContent());
    }
}
