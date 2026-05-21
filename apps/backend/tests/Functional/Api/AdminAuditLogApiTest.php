<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductReference;
use App\Entity\ProductReferenceProposal;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
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

    public function testAuditLogCreatedForStoreArchive(): void
    {
        $admin = $this->createUser('admin-store-archive-log@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-store-archive-log@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/stores/%s/archive', $shop->getId()),
            ['reason' => 'Fermeture définitive du commerce'],
            $admin,
        );

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $item = $this->decodeJson($response)['items'][0];
        self::assertSame('store.archive', $item['action']);
        self::assertSame($shop->getId()->toRfc4122(), $item['resource_id']);
        self::assertSame('Fermeture définitive du commerce', $item['metadata']['reason']);
        self::assertStringContainsString('archivée', $item['summary']);
    }

    public function testAuditLogCreatedForStoreCreateAndUpdate(): void
    {
        $admin = $this->createUser('admin-store-create-update-log@example.test', ['ROLE_ADMIN']);
        $owner = $this->createUser('merchant-store-owner-log@example.test', ['ROLE_MERCHANT']);

        $createResponse = $this->requestJson('POST', '/api/admin/stores', [
            'name' => 'Supérette Audit',
            'ownerId' => $owner->getId()->toRfc4122(),
        ], $admin);
        self::assertSame(201, $createResponse->getStatusCode());
        $storeId = $this->decodeJson($createResponse)['id'];

        $this->requestJson('PATCH', \sprintf('/api/admin/stores/%s', $storeId), [
            'name' => 'Supérette Audit Modifiée',
        ], $admin);

        $response = $this->requestJson('GET', '/api/admin/audit-logs?resource_type=store', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        $itemsByAction = [];
        foreach ($payload['items'] as $item) {
            $itemsByAction[$item['action']] = $item;
        }
        self::assertArrayHasKey('store.create', $itemsByAction);
        self::assertArrayHasKey('store.update', $itemsByAction);
        self::assertSame($storeId, $itemsByAction['store.update']['resource_id']);
        self::assertStringContainsString('modifiée', $itemsByAction['store.update']['summary']);
    }

    public function testAuditLogCreatedForMerchantUpdate(): void
    {
        $admin = $this->createUser('admin-merchant-update-log@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-update-log@example.test', ['ROLE_MERCHANT']);

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/merchants/%s', $merchant->getId()),
            ['first_name' => 'Ahmed', 'last_name' => 'Ben Salem'],
            $admin,
        );

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $item = $this->decodeJson($response)['items'][0];
        self::assertSame('merchant.update', $item['action']);
        self::assertSame($merchant->getId()->toRfc4122(), $item['resource_id']);
        self::assertStringContainsString('modifié', $item['summary']);
    }

    public function testAuditLogCreatedForProductReferenceCreateUpdateAndArchive(): void
    {
        $admin = $this->createUser('admin-product-reference-log@example.test', ['ROLE_ADMIN']);
        $brand = $this->makeBrand('Audit Brand');
        $category = $this->makeCategory('Audit Category');

        $createResponse = $this->requestJson('POST', '/api/admin/product-references', [
            'brandId' => $brand->getId()->toRfc4122(),
            'categoryId' => $category->getId()->toRfc4122(),
            'nameFr' => 'Produit Audit',
            'unit' => 'piece',
            'status' => 'approved',
        ], $admin);
        self::assertSame(201, $createResponse->getStatusCode());
        $productReferenceId = $this->decodeJson($createResponse)['id'];

        $this->requestJson('PATCH', \sprintf('/api/admin/product-references/%s', $productReferenceId), [
            'nameFr' => 'Produit Audit Modifié',
        ], $admin);
        $this->requestJson('PATCH', \sprintf('/api/admin/product-references/%s/archive', $productReferenceId), [], $admin);

        $response = $this->requestJson('GET', '/api/admin/audit-logs?resource_type=product_reference', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        $itemsByAction = [];
        foreach ($payload['items'] as $item) {
            $itemsByAction[$item['action']] = $item;
        }
        self::assertArrayHasKey('product_reference.create', $itemsByAction);
        self::assertArrayHasKey('product_reference.update', $itemsByAction);
        self::assertArrayHasKey('product_reference.archive', $itemsByAction);
        self::assertStringContainsString('archivé', $itemsByAction['product_reference.archive']['summary']);
    }

    public function testAuditLogCreatedForProductProposalApproveAndReject(): void
    {
        $admin = $this->createUser('admin-proposal-audit-log@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-proposal-audit-log@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->makeCategory('Propositions');
        $existingRef = $this->makeProductReference('Marque Proposition', $category, 'Produit existant');
        $approveProposal = $this->makeProposal($shop, $merchant, $category, 'Produit à valider');
        $rejectProposal = $this->makeProposal($shop, $merchant, $category, 'Produit à rejeter');

        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/approve', $approveProposal->getId()),
            ['productReferenceId' => $existingRef->getId()->toRfc4122()],
            $admin,
        );
        $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/reject', $rejectProposal->getId()),
            ['reason' => 'Doublon référentiel'],
            $admin,
        );

        $response = $this->requestJson('GET', '/api/admin/audit-logs?resource_type=product_proposal', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        $itemsByAction = [];
        foreach ($payload['items'] as $item) {
            $itemsByAction[$item['action']] = $item;
        }
        self::assertArrayHasKey('product_proposal.approve', $itemsByAction);
        self::assertArrayHasKey('product_proposal.reject', $itemsByAction);
        self::assertSame($existingRef->getId()->toRfc4122(), $itemsByAction['product_proposal.approve']['metadata']['product_reference_id']);
        self::assertSame('Doublon référentiel', $itemsByAction['product_proposal.reject']['metadata']['rejection_reason']);
        self::assertStringContainsString('validée', $itemsByAction['product_proposal.approve']['summary']);
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

    public function testFilterByAdmin(): void
    {
        $adminA = $this->createUser('admin-a-filter-audit@example.test', ['ROLE_ADMIN']);
        $adminB = $this->createUser('admin-b-filter-audit@example.test', ['ROLE_ADMIN']);
        $merchantA = $this->createUser('merchant-admin-a-filter@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-admin-b-filter@example.test', ['ROLE_MERCHANT']);

        $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $merchantA->getId()), [], $adminA);
        $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $merchantB->getId()), [], $adminB);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/admin/audit-logs?admin=%s', $adminA->getId()),
            user: $adminA,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame($adminA->getId()->toRfc4122(), $payload['items'][0]['admin_id']);
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
        self::assertArrayHasKey('summary', $item);
        self::assertArrayHasKey('created_at', $item);
    }

    public function testLongUserAgentIsTruncatedInAuditLog(): void
    {
        $admin = $this->createUser('admin-long-user-agent@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-long-user-agent@example.test', ['ROLE_MERCHANT']);
        $userAgent = str_repeat('Mozilla/5.0 ', 80);

        $this->requestJsonWithServer(
            'PATCH',
            \sprintf('/api/admin/merchants/%s/suspend', $merchant->getId()),
            [],
            $admin,
            ['HTTP_USER_AGENT' => $userAgent],
        );

        $response = $this->requestJson('GET', '/api/admin/audit-logs', user: $admin);
        $item = $this->decodeJson($response)['items'][0];

        self::assertSame(500, mb_strlen($item['user_agent']));
        self::assertSame(mb_substr($userAgent, 0, 500), $item['user_agent']);
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

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string>     $serverOverrides
     */
    private function requestJsonWithServer(
        string $method,
        string $path,
        ?array $payload,
        User $user,
        array $serverOverrides,
    ): Response {
        $server = array_replace([
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TEST_USER' => $user->getEmail(),
        ], $serverOverrides);
        $content = null;

        if (null !== $payload) {
            $server['CONTENT_TYPE'] = 'application/json';
            $content = json_encode($payload, \JSON_THROW_ON_ERROR);
        }

        $request = Request::create($path, $method, server: $server, content: $content);

        return self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
    }

    private function makeCategory(string $name): Category
    {
        $suffix = uniqid('', true);
        $category = (new Category())
            ->setNameFr($name)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '').$suffix);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function makeBrand(string $name): Brand
    {
        $suffix = uniqid('', true);
        $brand = (new Brand())
            ->setCanonicalName($name)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '').$suffix);

        $this->entityManager->persist($brand);
        $this->entityManager->flush();

        return $brand;
    }

    private function makeProductReference(string $brandName, Category $category, string $nameFr): ProductReference
    {
        $brand = $this->makeBrand($brandName);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setUnit(ProductUnit::Piece)
            ->setStatus(ProductReferenceStatus::Approved);

        $this->entityManager->persist($productReference);
        $this->entityManager->flush();

        return $productReference;
    }

    private function makeProposal(Shop $shop, User $proposedBy, Category $category, string $nameFr): ProductReferenceProposal
    {
        $proposal = (new ProductReferenceProposal())
            ->setShop($shop)
            ->setProposedBy($proposedBy)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setUnit(ProductUnit::Piece);

        $this->entityManager->persist($proposal);
        $this->entityManager->flush();

        return $proposal;
    }
}
