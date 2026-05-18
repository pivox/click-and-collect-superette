<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ExceptionalClosure;
use App\Entity\MerchantProduct;
use App\Entity\PickupSlotRule;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Symfony\Component\Uid\Uuid;

final class StoreAdminApiTest extends FunctionalApiTestCase
{
    public function testAdminSeesStores(): void
    {
        $admin = $this->createUser('admin-stores-list@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-store-list@example.test');
        $shop = $this->createStore(
            owner: $merchant,
            name: 'Supérette El Amal',
            slug: 'superette-el-amal',
            city: 'Tunis',
            createdAt: new \DateTimeImmutable('2026-05-18T10:00:00+00:00'),
        );
        $this->createMerchantProduct($shop, 'Lait Vitalait');
        $this->createMerchantProduct($shop, 'Eau Safia');

        $response = $this->requestJson('GET', '/api/admin/stores', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(20, $payload['limit']);
        self::assertCount(1, $payload['items']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('Supérette El Amal', $payload['items'][0]['name']);
        self::assertSame('superette-el-amal', $payload['items'][0]['slug']);
        self::assertSame('Tunis', $payload['items'][0]['city']);
        self::assertTrue($payload['items'][0]['is_active']);
        self::assertSame($shop->getQrCodeToken(), $payload['items'][0]['qr_code_token']);
        self::assertSame(2, $payload['items'][0]['products_count']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['items'][0]['owner']['id']);
        self::assertSame('merchant-store-list@example.test', $payload['items'][0]['owner']['email']);
        self::assertArrayHasKey('created_at', $payload['items'][0]);
        self::assertArrayNotHasKey('address', $payload['items'][0]);
        self::assertArrayNotHasKey('phone', $payload['items'][0]);
        self::assertArrayNotHasKey('theme_id', $payload['items'][0]);
        self::assertArrayNotHasKey('opening_hours', $payload['items'][0]);
        self::assertArrayNotHasKey('exceptional_closures_count', $payload['items'][0]);
        self::assertArrayNotHasKey('pickup_rules_count', $payload['items'][0]);
        self::assertArrayNotHasKey('password', $payload['items'][0]);
        self::assertArrayNotHasKey('password_hash', $payload['items'][0]);
        self::assertArrayNotHasKey('token', $payload['items'][0]);
        self::assertArrayNotHasKey('roles', $payload['items'][0]['owner']);
        self::assertArrayNotHasKey('password', $payload['items'][0]['owner']);
    }

    public function testAdminSeesStoreDetail(): void
    {
        $admin = $this->createUser('admin-store-detail@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-store-detail@example.test');
        $shop = $this->createStore(
            owner: $merchant,
            name: 'Supérette Détail',
            slug: 'superette-detail',
            city: 'Sfax',
            createdAt: new \DateTimeImmutable('2026-05-18T09:00:00+00:00'),
            address: 'Rue de la République',
            phone: '+21600000000',
            openingHours: [
                'timezone' => 'Africa/Tunis',
                'weekly' => [
                    '1' => [['start' => '08:00', 'end' => '12:00']],
                    '2' => [],
                    '3' => [],
                    '4' => [],
                    '5' => [],
                    '6' => [],
                    '7' => [],
                ],
            ],
        );
        $theme = $this->createShopTheme($shop);
        $this->createMerchantProduct($shop, 'Produit détail');
        $this->createPickupRule($shop);
        $this->createExceptionalClosure($shop);

        $response = $this->requestJson('GET', \sprintf('/api/admin/stores/%s', $shop->getId()), user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['id']);
        self::assertSame('Supérette Détail', $payload['name']);
        self::assertSame('superette-detail', $payload['slug']);
        self::assertSame('Rue de la République', $payload['address']);
        self::assertSame('Sfax', $payload['city']);
        self::assertSame('+21600000000', $payload['phone']);
        self::assertTrue($payload['is_active']);
        self::assertSame($shop->getQrCodeToken(), $payload['qr_code_token']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['owner']['id']);
        self::assertSame('merchant-store-detail@example.test', $payload['owner']['email']);
        self::assertSame(1, $payload['products_count']);
        self::assertSame($theme->getId()->toRfc4122(), $payload['theme_id']);
        self::assertSame('Africa/Tunis', $payload['opening_hours']['timezone']);
        self::assertSame(1, $payload['exceptional_closures_count']);
        self::assertSame(1, $payload['pickup_rules_count']);
        self::assertArrayNotHasKey('password', $payload);
        self::assertArrayNotHasKey('password_hash', $payload);
        self::assertArrayNotHasKey('token', $payload);
        self::assertArrayNotHasKey('roles', $payload['owner']);
    }

    public function testStoreListPaginationAndDescendingSort(): void
    {
        $admin = $this->createUser('admin-stores-pagination@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-store-pagination@example.test');
        $oldest = $this->createStore($merchant, 'Old Store', 'old-store', 'Tunis', new \DateTimeImmutable('2026-05-18T08:00:00+00:00'));
        $middle = $this->createStore($merchant, 'Middle Store', 'middle-store', 'Tunis', new \DateTimeImmutable('2026-05-18T09:00:00+00:00'));
        $newest = $this->createStore($merchant, 'Newest Store', 'newest-store', 'Tunis', new \DateTimeImmutable('2026-05-18T10:00:00+00:00'));

        $pageOneResponse = $this->requestJson('GET', '/api/admin/stores?page=1&limit=2', user: $admin);
        $pageTwoResponse = $this->requestJson('GET', '/api/admin/stores?page=2&limit=2', user: $admin);
        $emptyPageResponse = $this->requestJson('GET', '/api/admin/stores?page=99&limit=2', user: $admin);

        self::assertSame(200, $pageOneResponse->getStatusCode());
        self::assertSame(200, $pageTwoResponse->getStatusCode());
        self::assertSame(200, $emptyPageResponse->getStatusCode());

        $pageOne = $this->decodeJson($pageOneResponse);
        self::assertSame(3, $pageOne['total']);
        self::assertSame(1, $pageOne['page']);
        self::assertSame(2, $pageOne['limit']);
        self::assertSame($newest->getId()->toRfc4122(), $pageOne['items'][0]['id']);
        self::assertSame($middle->getId()->toRfc4122(), $pageOne['items'][1]['id']);

        $pageTwo = $this->decodeJson($pageTwoResponse);
        self::assertSame(3, $pageTwo['total']);
        self::assertSame(2, $pageTwo['page']);
        self::assertSame(2, $pageTwo['limit']);
        self::assertCount(1, $pageTwo['items']);
        self::assertSame($oldest->getId()->toRfc4122(), $pageTwo['items'][0]['id']);

        $emptyPage = $this->decodeJson($emptyPageResponse);
        self::assertSame(3, $emptyPage['total']);
        self::assertSame(99, $emptyPage['page']);
        self::assertSame(2, $emptyPage['limit']);
        self::assertSame([], $emptyPage['items']);
    }

    public function testStoreListUsesIdDescendingAsStableTieBreaker(): void
    {
        $admin = $this->createUser('admin-stores-sort-tie@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-store-sort-tie@example.test');
        $createdAt = new \DateTimeImmutable('2026-05-18T10:00:00+00:00');
        $first = $this->createStore($merchant, 'Same Date A', 'same-date-a', 'Tunis', $createdAt);
        $second = $this->createStore($merchant, 'Same Date B', 'same-date-b', 'Tunis', $createdAt);
        $expectedIds = [$first->getId()->toRfc4122(), $second->getId()->toRfc4122()];
        rsort($expectedIds, \SORT_STRING);

        $response = $this->requestJson('GET', '/api/admin/stores?page=1&limit=2', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($expectedIds, array_column($payload['items'], 'id'));
    }

    public function testStoreListCanFilterByActiveState(): void
    {
        $admin = $this->createUser('admin-stores-active-filter@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-store-active-filter@example.test');
        $activeStore = $this->createStore(
            $merchant,
            'Active Store',
            'active-store',
            'Tunis',
            new \DateTimeImmutable('2026-05-18T10:00:00+00:00'),
            active: true,
        );
        $inactiveStore = $this->createStore(
            $merchant,
            'Inactive Store',
            'inactive-store',
            'Tunis',
            new \DateTimeImmutable('2026-05-18T09:00:00+00:00'),
            active: false,
        );

        $activeResponse = $this->requestJson('GET', '/api/admin/stores?is_active=true', user: $admin);
        $inactiveResponse = $this->requestJson('GET', '/api/admin/stores?is_active=false', user: $admin);
        $invalidResponse = $this->requestJson('GET', '/api/admin/stores?is_active=maybe', user: $admin);

        self::assertSame(200, $activeResponse->getStatusCode());
        $activePayload = $this->decodeJson($activeResponse);
        self::assertSame(1, $activePayload['total']);
        self::assertSame($activeStore->getId()->toRfc4122(), $activePayload['items'][0]['id']);

        self::assertSame(200, $inactiveResponse->getStatusCode());
        $inactivePayload = $this->decodeJson($inactiveResponse);
        self::assertSame(1, $inactivePayload['total']);
        self::assertSame($inactiveStore->getId()->toRfc4122(), $inactivePayload['items'][0]['id']);

        self::assertSame(400, $invalidResponse->getStatusCode());
    }

    public function testLimitIsCappedAt50AndMalformedPaginationReturnsBadRequest(): void
    {
        $admin = $this->createUser('admin-stores-limit@example.test', ['ROLE_ADMIN']);

        $cappedResponse = $this->requestJson('GET', '/api/admin/stores?limit=100', user: $admin);
        $pageResponse = $this->requestJson('GET', '/api/admin/stores?page=abc', user: $admin);
        $limitResponse = $this->requestJson('GET', '/api/admin/stores?limit=0', user: $admin);

        self::assertSame(200, $cappedResponse->getStatusCode());
        self::assertSame(50, $this->decodeJson($cappedResponse)['limit']);
        self::assertSame(400, $pageResponse->getStatusCode());
        self::assertSame(400, $limitResponse->getStatusCode());
    }

    public function testStoreWithoutOwnerIsReturnedWithNullOwner(): void
    {
        $admin = $this->createUser('admin-store-no-owner@example.test', ['ROLE_ADMIN']);
        $shop = $this->createStore(null, 'Store sans marchand', 'store-sans-marchand', 'Ariana', new \DateTimeImmutable('2026-05-18T10:00:00+00:00'));

        $response = $this->requestJson('GET', \sprintf('/api/admin/stores/%s', $shop->getId()), user: $admin);

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($this->decodeJson($response)['owner']);
    }

    public function testCustomerMerchantAndAnonymousAreDenied(): void
    {
        $merchant = $this->createMerchant('merchant-store-admin-forbidden@example.test');
        $customer = $this->createUser('customer-store-admin-forbidden@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createStore($merchant, 'Store forbidden', 'store-forbidden', 'Tunis', new \DateTimeImmutable());

        $customerListResponse = $this->requestJson('GET', '/api/admin/stores', user: $customer);
        $customerDetailResponse = $this->requestJson('GET', \sprintf('/api/admin/stores/%s', $shop->getId()), user: $customer);
        $merchantListResponse = $this->requestJson('GET', '/api/admin/stores', user: $merchant);
        $merchantDetailResponse = $this->requestJson('GET', \sprintf('/api/admin/stores/%s', $shop->getId()), user: $merchant);
        $anonymousListResponse = $this->requestJson('GET', '/api/admin/stores');
        $anonymousDetailResponse = $this->requestJson('GET', \sprintf('/api/admin/stores/%s', $shop->getId()));

        self::assertSame(403, $customerListResponse->getStatusCode());
        self::assertSame(403, $customerDetailResponse->getStatusCode());
        self::assertSame(403, $merchantListResponse->getStatusCode());
        self::assertSame(403, $merchantDetailResponse->getStatusCode());
        self::assertSame(401, $anonymousListResponse->getStatusCode());
        self::assertSame(401, $anonymousDetailResponse->getStatusCode());
    }

    public function testMissingStoreReturnsNotFound(): void
    {
        $admin = $this->createUser('admin-store-missing@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', \sprintf('/api/admin/stores/%s', Uuid::v4()), user: $admin);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testAdminCreatesStoreWithGeneratedSlugAndQrCodeToken(): void
    {
        $admin = $this->createUser('admin-store-create@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-store-create@example.test');

        $response = $this->requestJson('POST', '/api/admin/stores', [
            'name' => 'Supérette El Bahja',
            'address' => '12 Rue de Tunis',
            'city' => 'Tunis',
            'phone' => '+21611111111',
            'ownerId' => $merchant->getId()->toRfc4122(),
        ], user: $admin);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('Supérette El Bahja', $payload['name']);
        self::assertSame('superette-el-bahja', $payload['slug']);
        self::assertSame('12 Rue de Tunis', $payload['address']);
        self::assertSame('Tunis', $payload['city']);
        self::assertSame('+21611111111', $payload['phone']);
        self::assertTrue($payload['is_active']);
        self::assertNotEmpty($payload['qr_code_token']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['owner']['id']);
        self::assertSame('merchant-store-create@example.test', $payload['owner']['email']);

        $shop = $this->entityManager->getRepository(Shop::class)->find($payload['id']);
        self::assertInstanceOf(Shop::class, $shop);
        self::assertSame('superette-el-bahja', $shop->getSlug());
        self::assertSame($payload['qr_code_token'], $shop->getQrCodeToken());
    }

    public function testDuplicateSlugGetsSuffix(): void
    {
        $admin = $this->createUser('admin-store-create-duplicate@example.test', ['ROLE_ADMIN']);
        $this->createStore(null, 'Supérette El Bahja', 'superette-el-bahja', 'Tunis', new \DateTimeImmutable());

        $response = $this->requestJson('POST', '/api/admin/stores', [
            'name' => 'Supérette El Bahja',
            'city' => 'Ariana',
        ], user: $admin);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('superette-el-bahja-2', $this->decodeJson($response)['slug']);
    }

    public function testAdminUpdatesStoreAndDoesNotRegenerateSlugOrQrCodeToken(): void
    {
        $admin = $this->createUser('admin-store-update@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-store-update@example.test');
        $shop = $this->createStore(null, 'Store update', 'store-update', 'Tunis', new \DateTimeImmutable());
        $originalQrCodeToken = $shop->getQrCodeToken();

        $response = $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s', $shop->getId()), [
            'name' => 'Supérette Update',
            'address' => '14 Rue de Sousse',
            'city' => 'Sousse',
            'phone' => '+21622222222',
            'isActive' => false,
            'ownerId' => $merchant->getId()->toRfc4122(),
        ], user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('Supérette Update', $payload['name']);
        self::assertSame('store-update', $payload['slug']);
        self::assertSame($originalQrCodeToken, $payload['qr_code_token']);
        self::assertSame('14 Rue de Sousse', $payload['address']);
        self::assertSame('Sousse', $payload['city']);
        self::assertSame('+21622222222', $payload['phone']);
        self::assertFalse($payload['is_active']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['owner']['id']);
    }

    public function testAdminCanClearStoreOwner(): void
    {
        $admin = $this->createUser('admin-store-clear-owner@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-store-clear-owner@example.test');
        $shop = $this->createStore($merchant, 'Store owner clear', 'store-owner-clear', 'Tunis', new \DateTimeImmutable());

        $response = $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s', $shop->getId()), [
            'ownerId' => null,
        ], user: $admin);

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($this->decodeJson($response)['owner']);
    }

    public function testCustomerMerchantAndAnonymousCannotCreateOrUpdateStores(): void
    {
        $merchant = $this->createMerchant('merchant-store-write-forbidden@example.test');
        $customer = $this->createUser('customer-store-write-forbidden@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createStore($merchant, 'Store write forbidden', 'store-write-forbidden', 'Tunis', new \DateTimeImmutable());
        $payload = ['name' => 'Forbidden Store', 'city' => 'Tunis'];

        self::assertSame(403, $this->requestJson('POST', '/api/admin/stores', $payload, user: $customer)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s', $shop->getId()), $payload, user: $customer)->getStatusCode());
        self::assertSame(403, $this->requestJson('POST', '/api/admin/stores', $payload, user: $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s', $shop->getId()), $payload, user: $merchant)->getStatusCode());
        self::assertSame(401, $this->requestJson('POST', '/api/admin/stores', $payload)->getStatusCode());
        self::assertSame(401, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s', $shop->getId()), $payload)->getStatusCode());
    }

    public function testUpdateMissingStoreReturnsNotFound(): void
    {
        $admin = $this->createUser('admin-store-update-missing@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s', Uuid::v4()), [
            'name' => 'Missing Store',
        ], user: $admin);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testInvalidCreateAndUpdatePayloadsReturnUnprocessableEntity(): void
    {
        $admin = $this->createUser('admin-store-invalid-payload@example.test', ['ROLE_ADMIN']);
        $shop = $this->createStore(null, 'Store invalid payload', 'store-invalid-payload', 'Tunis', new \DateTimeImmutable());

        $createResponse = $this->requestJson('POST', '/api/admin/stores', [
            'name' => '',
            'phone' => str_repeat('1', 21),
            'ownerId' => 'not-a-uuid',
        ], user: $admin);
        $updateResponse = $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s', $shop->getId()), [
            'name' => '',
            'phone' => str_repeat('1', 21),
            'isActive' => 'yes',
            'ownerId' => 'not-a-uuid',
        ], user: $admin);

        self::assertSame(422, $createResponse->getStatusCode());
        self::assertSame(422, $updateResponse->getStatusCode());
    }

    public function testCreateWithWhitespaceOnlyNameReturnsUnprocessableEntity(): void
    {
        $admin = $this->createUser('admin-store-whitespace-create@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/stores', [
            'name' => '   ',
        ], user: $admin);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateWithWhitespaceOnlyNameReturnsUnprocessableEntity(): void
    {
        $admin = $this->createUser('admin-store-whitespace-update@example.test', ['ROLE_ADMIN']);
        $shop = $this->createStore(null, 'Store whitespace name', 'store-whitespace-name', 'Tunis', new \DateTimeImmutable());
        $originalName = $shop->getName();

        $response = $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s', $shop->getId()), [
            'name' => '   ',
        ], user: $admin);

        self::assertSame(422, $response->getStatusCode());

        $this->entityManager->refresh($shop);
        self::assertSame($originalName, $shop->getName());
    }

    public function testAdminActivatesStore(): void
    {
        $admin = $this->createUser('admin-activate-store@example.test', ['ROLE_ADMIN']);
        $shop = $this->createStore(null, 'Store Inactif', 'store-inactif-activate', 'Tunis', new \DateTimeImmutable(), active: false);

        $response = $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/activate', $shop->getId()), [], $admin);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->decodeJson($response)['is_active']);
    }

    public function testAdminDeactivatesStore(): void
    {
        $admin = $this->createUser('admin-deactivate-store@example.test', ['ROLE_ADMIN']);
        $shop = $this->createStore(null, 'Store Actif', 'store-actif-deactivate', 'Tunis', new \DateTimeImmutable(), active: true);

        $response = $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/deactivate', $shop->getId()), [], $admin);

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($this->decodeJson($response)['is_active']);
    }

    public function testActivateDeactivateForbiddenForNonAdmin(): void
    {
        $merchant = $this->createMerchant('merchant-activate-forbidden@example.test');
        $customer = $this->createUser('customer-activate-forbidden@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createStore(null, 'Store Activate Forbidden', 'store-activate-forbidden', 'Tunis', new \DateTimeImmutable());

        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/activate', $shop->getId()), [], $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/deactivate', $shop->getId()), [], $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/activate', $shop->getId()), [], $customer)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/deactivate', $shop->getId()), [], $customer)->getStatusCode());
        self::assertSame(401, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/activate', $shop->getId()), [])->getStatusCode());
        self::assertSame(401, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/deactivate', $shop->getId()), [])->getStatusCode());
    }

    public function testActivateDeactivateMissingStoreReturnsNotFound(): void
    {
        $admin = $this->createUser('admin-activate-missing@example.test', ['ROLE_ADMIN']);
        $unknownId = Uuid::v4()->toRfc4122();

        self::assertSame(404, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/activate', $unknownId), [], $admin)->getStatusCode());
        self::assertSame(404, $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/deactivate', $unknownId), [], $admin)->getStatusCode());
    }

    public function testAdminGetsStoreQrCode(): void
    {
        $admin = $this->createUser('admin-store-qr-read@example.test', ['ROLE_ADMIN']);
        $shop = $this->createStore(null, 'Store QR Read', 'store-qr-read', 'Tunis', new \DateTimeImmutable());

        $response = $this->requestJson('GET', \sprintf('/api/admin/stores/%s/qr-code', $shop->getId()), user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame('Store QR Read', $payload['store_name']);
        self::assertSame('store-qr-read', $payload['slug']);
        self::assertSame($shop->getQrCodeToken(), $payload['qr_code_token']);
        self::assertSame(\sprintf('/api/stores/by-qr/%s', $shop->getQrCodeToken()), $payload['target_url']);
        self::assertArrayNotHasKey('qr_payload', $payload);
        self::assertArrayNotHasKey('password', $payload);
        self::assertArrayNotHasKey('password_hash', $payload);
        self::assertArrayNotHasKey('token', $payload);
        self::assertArrayNotHasKey('owner', $payload);
    }

    public function testAdminRegeneratesQrToken(): void
    {
        $admin = $this->createUser('admin-store-qr-regenerate@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-store-qr-regenerate@example.test');
        $shop = $this->createStore(
            $merchant,
            'Store QR Regenerate',
            'store-qr-regenerate',
            'Sousse',
            new \DateTimeImmutable(),
            address: 'Rue QR',
            phone: '+21622222222',
        );
        $oldToken = $shop->getQrCodeToken();
        $oldName = $shop->getName();
        $oldSlug = $shop->getSlug();
        $oldCity = $shop->getCity();
        $oldAddress = $shop->getAddress();
        $oldPhone = $shop->getPhone();
        $oldOwnerId = $shop->getOwner()?->getId()->toRfc4122();

        $response = $this->requestJson('POST', \sprintf('/api/admin/stores/%s/regenerate-qr', $shop->getId()), [], $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame($oldName, $payload['store_name']);
        self::assertSame($oldSlug, $payload['slug']);
        self::assertNotSame($oldToken, $payload['qr_code_token']);
        self::assertSame(\sprintf('/api/stores/by-qr/%s', $payload['qr_code_token']), $payload['target_url']);

        $this->entityManager->refresh($shop);
        self::assertSame($payload['qr_code_token'], $shop->getQrCodeToken());
        self::assertSame($oldName, $shop->getName());
        self::assertSame($oldSlug, $shop->getSlug());
        self::assertSame($oldCity, $shop->getCity());
        self::assertSame($oldAddress, $shop->getAddress());
        self::assertSame($oldPhone, $shop->getPhone());
        self::assertSame($oldOwnerId, $shop->getOwner()?->getId()->toRfc4122());

        self::assertSame(404, $this->requestJson('GET', \sprintf('/api/stores/by-qr/%s', $oldToken))->getStatusCode());
        self::assertSame(200, $this->requestJson('GET', \sprintf('/api/stores/by-qr/%s', $payload['qr_code_token']))->getStatusCode());
    }

    public function testQrCodeEndpointsAreForbiddenForNonAdmin(): void
    {
        $merchant = $this->createMerchant('merchant-store-qr-forbidden@example.test');
        $customer = $this->createUser('customer-store-qr-forbidden@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createStore($merchant, 'Store QR Forbidden', 'store-qr-forbidden', 'Tunis', new \DateTimeImmutable());

        self::assertSame(403, $this->requestJson('GET', \sprintf('/api/admin/stores/%s/qr-code', $shop->getId()), user: $customer)->getStatusCode());
        self::assertSame(403, $this->requestJson('POST', \sprintf('/api/admin/stores/%s/regenerate-qr', $shop->getId()), [], $customer)->getStatusCode());
        self::assertSame(403, $this->requestJson('GET', \sprintf('/api/admin/stores/%s/qr-code', $shop->getId()), user: $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('POST', \sprintf('/api/admin/stores/%s/regenerate-qr', $shop->getId()), [], $merchant)->getStatusCode());
        self::assertSame(401, $this->requestJson('GET', \sprintf('/api/admin/stores/%s/qr-code', $shop->getId()))->getStatusCode());
        self::assertSame(401, $this->requestJson('POST', \sprintf('/api/admin/stores/%s/regenerate-qr', $shop->getId()), [])->getStatusCode());
    }

    public function testQrCodeEndpointsReturnNotFoundForMissingStore(): void
    {
        $admin = $this->createUser('admin-store-qr-missing@example.test', ['ROLE_ADMIN']);
        $unknownId = Uuid::v4()->toRfc4122();

        self::assertSame(404, $this->requestJson('GET', \sprintf('/api/admin/stores/%s/qr-code', $unknownId), user: $admin)->getStatusCode());
        self::assertSame(404, $this->requestJson('POST', \sprintf('/api/admin/stores/%s/regenerate-qr', $unknownId), [], $admin)->getStatusCode());
    }

    private function createMerchant(string $email): User
    {
        return $this->createUser($email, ['ROLE_MERCHANT']);
    }

    /**
     * @param array<string, mixed>|null $openingHours
     */
    private function createStore(
        ?User $owner,
        string $name,
        string $slug,
        string $city,
        \DateTimeImmutable $createdAt,
        ?array $openingHours = null,
        ?string $address = null,
        ?string $phone = null,
        bool $active = true,
    ): Shop {
        $shop = $this->createShop($owner);
        $shop
            ->setName($name)
            ->setSlug($slug)
            ->setCity($city)
            ->setAddress($address)
            ->setPhone($phone)
            ->setActive($active)
            ->setOpeningHours($openingHours);
        $this->setPrivateProperty($shop, 'createdAt', $createdAt);
        $this->setPrivateProperty($shop, 'updatedAt', $createdAt);
        $this->entityManager->flush();

        return $shop;
    }

    private function createMerchantProduct(Shop $shop, string $name): MerchantProduct
    {
        $productReference = $this->createProductReference($name);
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('2.800')
            ->setAvailable(true)
            ->setVisible(true);

        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }

    private function createProductReference(string $name): ProductReference
    {
        $id = Uuid::v4()->toRfc4122();
        $brand = (new Brand())
            ->setCanonicalName('Brand '.$id)
            ->setSlug('brand-'.$id)
            ->setActive(true);
        $category = (new Category())
            ->setNameFr('Catégorie '.$id)
            ->setSlug('categorie-'.$id)
            ->setActive(true);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($name)
            ->setUnit(ProductUnit::Piece)
            ->setStatus(ProductReferenceStatus::Approved);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();

        return $productReference;
    }

    private function createPickupRule(Shop $shop): PickupSlotRule
    {
        $rule = (new PickupSlotRule())
            ->setShop($shop)
            ->setWeekday(1)
            ->setStartTime(new \DateTimeImmutable('09:00'))
            ->setEndTime(new \DateTimeImmutable('12:00'))
            ->setCapacity(5)
            ->setActive(true);

        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        return $rule;
    }

    private function createExceptionalClosure(Shop $shop): ExceptionalClosure
    {
        $closure = (new ExceptionalClosure())
            ->setShop($shop)
            ->setStartsAt(new \DateTimeImmutable('2026-05-20T08:00:00+01:00'))
            ->setEndsAt(new \DateTimeImmutable('2026-05-20T18:00:00+01:00'))
            ->setReason('Inventaire')
            ->setActive(true);

        $this->entityManager->persist($closure);
        $this->entityManager->flush();

        return $closure;
    }
}
