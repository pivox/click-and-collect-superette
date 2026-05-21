<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Symfony\Component\Uid\Uuid;

final class AdminAuditLogApiTest extends FunctionalApiTestCase
{
    // --- Access control ---

    public function testAnonymousIsUnauthorized(): void
    {
        $response = $this->requestJson('GET', '/api/admin/audit-logs');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testCustomerIsForbidden(): void
    {
        $customer = $this->createUser('customer-audit-forbidden@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $customer);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testMerchantIsForbidden(): void
    {
        $merchant = $this->createUser('merchant-audit-forbidden@example.test', ['ROLE_MERCHANT']);
        $merchant->setPassword('test-password');
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $merchant);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testPostIsNotAllowed(): void
    {
        $admin = $this->createUser('admin-post-audit@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/audit-logs', [], $admin);

        self::assertSame(405, $response->getStatusCode());
    }

    // --- Collection listing ---

    public function testAdminSeesEmptyList(): void
    {
        $admin = $this->createUser('admin-empty-audit@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(0, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(20, $payload['limit']);
        self::assertCount(0, $payload['items']);
    }

    public function testAdminSeesAuditLogAfterMerchantSuspend(): void
    {
        $admin = $this->createUser('admin-suspend-log@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-to-suspend-log@example.test', ['ROLE_MERCHANT']);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/merchants/%s/suspend', $merchant->getId()),
            [],
            $admin,
        );

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);

        $item = $payload['items'][0];
        self::assertArrayHasKey('id', $item);
        self::assertSame('merchant.suspend', $item['action']);
        self::assertSame('merchant', $item['resource_type']);
        self::assertSame($merchant->getId()->toRfc4122(), $item['resource_id']);
        self::assertSame($admin->getId()->toRfc4122(), $item['admin_id']);
        self::assertSame('admin-suspend-log@example.test', $item['admin_email']);
        self::assertArrayHasKey('created_at', $item);
    }

    public function testAuditLogCreatedForMerchantCreate(): void
    {
        $admin = $this->createUser('admin-create-log@example.test', ['ROLE_ADMIN']);

        $this->requestJson('POST', '/api/admin/merchants', [
            'email' => 'new-merchant-log@example.test',
            'first_name' => 'Log',
            'last_name' => 'Test',
        ], $admin);

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame('merchant.create', $payload['items'][0]['action']);
        self::assertSame('merchant', $payload['items'][0]['resource_type']);
    }

    public function testAuditLogCreatedForMerchantActivate(): void
    {
        $admin = $this->createUser('admin-activate-log@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-to-activate-log@example.test', ['ROLE_MERCHANT']);
        $merchant->setActive(false);
        $this->entityManager->flush();

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/merchants/%s/activate', $merchant->getId()),
            [],
            $admin,
        );

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame('merchant.activate', $payload['items'][0]['action']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['items'][0]['resource_id']);
    }

    public function testAuditLogCreatedForStoreActivate(): void
    {
        $admin = $this->createUser('admin-store-activate-log@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-store-log@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant, false);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/activate', $shop->getId()),
            [],
            $admin,
        );

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame('store.activate', $payload['items'][0]['action']);
        self::assertSame('store', $payload['items'][0]['resource_type']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['items'][0]['resource_id']);
    }

    public function testAuditLogCreatedForStoreDeactivate(): void
    {
        $admin = $this->createUser('admin-store-deactivate-log@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-store-deact-log@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/deactivate', $shop->getId()),
            [],
            $admin,
        );

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame('store.deactivate', $payload['items'][0]['action']);
    }

    public function testAuditLogCreatedForStoreQrRegenerate(): void
    {
        $admin = $this->createUser('admin-qr-log@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-qr-log@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $this->requestJson(
            'POST',
            \sprintf('/api/admin/stores/%s/regenerate-qr', $shop->getId()),
            [],
            $admin,
        );

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame('store.qr_regenerate', $payload['items'][0]['action']);
    }

    // --- Filters ---

    public function testFilterByAction(): void
    {
        $admin = $this->createUser('admin-filter-action@example.test', ['ROLE_ADMIN']);
        $m1 = $this->createUser('m1-filter@example.test', ['ROLE_MERCHANT']);
        $m2 = $this->createUser('m2-filter@example.test', ['ROLE_MERCHANT']);
        $m2->setActive(false);
        $this->entityManager->flush();

        $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $m1->getId()), [], $admin);
        $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/activate', $m2->getId()), [], $admin);

        $response = $this->requestJson('GET', '/api/admin/audit-logs?action=merchant.suspend', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame('merchant.suspend', $payload['items'][0]['action']);
    }

    public function testFilterByResourceType(): void
    {
        $admin = $this->createUser('admin-filter-type@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-type-filter@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $merchant->getId()), [], $admin);
        $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s/deactivate', $shop->getId()), [], $admin);

        $response = $this->requestJson('GET', '/api/admin/audit-logs?resource_type=store', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame('store', $payload['items'][0]['resource_type']);
    }

    public function testFilterByResourceId(): void
    {
        $admin = $this->createUser('admin-filter-id@example.test', ['ROLE_ADMIN']);
        $m1 = $this->createUser('m1-resource-id@example.test', ['ROLE_MERCHANT']);
        $m2 = $this->createUser('m2-resource-id@example.test', ['ROLE_MERCHANT']);

        $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $m1->getId()), [], $admin);
        $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $m2->getId()), [], $admin);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/admin/audit-logs?resource_id=%s', $m1->getId()),
            user: $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame($m1->getId()->toRfc4122(), $payload['items'][0]['resource_id']);
    }

    // --- Pagination ---

    public function testPaginationWorks(): void
    {
        $admin = $this->createUser('admin-pagination-audit@example.test', ['ROLE_ADMIN']);
        $m1 = $this->createUser('m1-pag@example.test', ['ROLE_MERCHANT']);
        $m2 = $this->createUser('m2-pag@example.test', ['ROLE_MERCHANT']);
        $m2b = $this->createUser('m2b-pag@example.test', ['ROLE_MERCHANT']);

        $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $m1->getId()), [], $admin);
        $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $m2->getId()), [], $admin);
        $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $m2b->getId()), [], $admin);

        $pageOne = $this->decodeJson($this->requestJson('GET', '/api/admin/audit-logs?page=1&limit=2', user: $admin));
        $pageTwo = $this->decodeJson($this->requestJson('GET', '/api/admin/audit-logs?page=2&limit=2', user: $admin));

        self::assertSame(3, $pageOne['total']);
        self::assertSame(2, $pageOne['limit']);
        self::assertCount(2, $pageOne['items']);

        self::assertSame(3, $pageTwo['total']);
        self::assertSame(2, $pageTwo['page']);
        self::assertCount(1, $pageTwo['items']);
    }

    public function testLimitIsCappedAt50(): void
    {
        $admin = $this->createUser('admin-limit-audit@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/audit-logs?limit=100', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(50, $this->decodeJson($response)['limit']);
    }

    // --- Metadata safety ---

    public function testMetadataContainsNoSensitiveData(): void
    {
        $admin = $this->createUser('admin-metadata-safe@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-meta@example.test', ['ROLE_MERCHANT']);

        $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $merchant->getId()), [], $admin);

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $admin);
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringNotContainsStringIgnoringCase('"password"', $content);
        self::assertStringNotContainsStringIgnoringCase('"token"', $content);
        self::assertStringNotContainsStringIgnoringCase('"roles"', $content);
        self::assertStringNotContainsStringIgnoringCase('"secret"', $content);
    }

    public function testItemStructureIsComplete(): void
    {
        $admin = $this->createUser('admin-item-structure@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-item-struct@example.test', ['ROLE_MERCHANT']);

        $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $merchant->getId()), [], $admin);

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $admin);
        $item = $this->decodeJson($response)['items'][0];

        self::assertArrayHasKey('id', $item);
        self::assertArrayHasKey('action', $item);
        self::assertArrayHasKey('resource_type', $item);
        self::assertArrayHasKey('resource_id', $item);
        self::assertArrayHasKey('admin_id', $item);
        self::assertArrayHasKey('admin_email', $item);
        self::assertArrayHasKey('created_at', $item);
    }

    // --- Invalid params ---

    public function testMalformedPageReturns400(): void
    {
        $admin = $this->createUser('admin-malformed-page-audit@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/audit-logs?page=abc', user: $admin);

        self::assertSame(400, $response->getStatusCode());
    }

    public function testMalformedLimitReturns400(): void
    {
        $admin = $this->createUser('admin-malformed-limit-audit@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/audit-logs?limit=0', user: $admin);

        self::assertSame(400, $response->getStatusCode());
    }

    // --- Non-existing resource ID gives empty results ---

    public function testFilterByUnknownResourceIdReturnsEmpty(): void
    {
        $admin = $this->createUser('admin-unknown-resource@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/admin/audit-logs?resource_id=%s', Uuid::v4()),
            user: $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $this->decodeJson($response)['total']);
    }
}
