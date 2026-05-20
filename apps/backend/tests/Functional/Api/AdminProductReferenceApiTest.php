<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;

final class AdminProductReferenceApiTest extends FunctionalApiTestCase
{
    // ── LIST ──────────────────────────────────────────────────────────────────

    public function testAdminListsProductReferences(): void
    {
        $admin = $this->createUser('admin-pr-list@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Test', 'marque-test');
        $category = $this->createCategory('Épicerie', 'epicerie');
        $ref = $this->createProductReference($brand, $category, 'Huile d\'olive');

        $response = $this->requestJson('GET', '/api/admin/product-references', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(20, $payload['limit']);
        self::assertCount(1, $payload['items']);
        self::assertSame($ref->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('Huile d\'olive', $payload['items'][0]['name_fr']);
        self::assertSame($brand->getId()->toRfc4122(), $payload['items'][0]['brand_id']);
        self::assertSame('Marque Test', $payload['items'][0]['brand_name']);
        self::assertSame($category->getId()->toRfc4122(), $payload['items'][0]['category_id']);
        self::assertSame('Épicerie', $payload['items'][0]['category_name_fr']);
        self::assertSame('draft', $payload['items'][0]['status']);
        self::assertArrayHasKey('created_at', $payload['items'][0]);
        self::assertArrayHasKey('updated_at', $payload['items'][0]);
    }

    public function testListPaginationDefaultsAndLimit(): void
    {
        $admin = $this->createUser('admin-pr-pagination@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Pag', 'marque-pag');
        $category = $this->createCategory('Boissons Pag', 'boissons-pag');
        for ($i = 1; $i <= 3; ++$i) {
            $this->createProductReference($brand, $category, 'Produit '.$i);
        }

        $pageOne = $this->decodeJson($this->requestJson('GET', '/api/admin/product-references?page=1&limit=2', user: $admin));
        $pageTwo = $this->decodeJson($this->requestJson('GET', '/api/admin/product-references?page=2&limit=2', user: $admin));

        self::assertSame(3, $pageOne['total']);
        self::assertSame(2, $pageOne['limit']);
        self::assertCount(2, $pageOne['items']);
        self::assertSame(3, $pageTwo['total']);
        self::assertCount(1, $pageTwo['items']);
    }

    public function testListPaginationInvalidPageReturns400(): void
    {
        $admin = $this->createUser('admin-pr-invalid-page@example.test', ['ROLE_ADMIN']);

        self::assertSame(400, $this->requestJson('GET', '/api/admin/product-references?page=abc', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/product-references?page=0', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/product-references?limit=abc', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/product-references?limit=0', user: $admin)->getStatusCode());
    }

    public function testListLimitIsCappedAtFifty(): void
    {
        $admin = $this->createUser('admin-pr-limit-cap@example.test', ['ROLE_ADMIN']);

        $payload = $this->decodeJson($this->requestJson('GET', '/api/admin/product-references?limit=100', user: $admin));

        self::assertSame(50, $payload['limit']);
    }

    public function testListFiltersByQ(): void
    {
        $admin = $this->createUser('admin-pr-filter-q@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Q', 'marque-q');
        $category = $this->createCategory('Catégorie Q', 'categorie-q');
        $this->createProductReference($brand, $category, 'Lait entier');
        $this->createProductReference($brand, $category, 'Yaourt nature');

        $payload = $this->decodeJson($this->requestJson('GET', '/api/admin/product-references?q=lait', user: $admin));

        self::assertSame(1, $payload['total']);
        self::assertSame('Lait entier', $payload['items'][0]['name_fr']);
    }

    public function testListFiltersByBrand(): void
    {
        $admin = $this->createUser('admin-pr-filter-brand@example.test', ['ROLE_ADMIN']);
        $brand1 = $this->createBrand('Marque A', 'marque-a');
        $brand2 = $this->createBrand('Marque B', 'marque-b');
        $category = $this->createCategory('Catégorie Brand', 'categorie-brand');
        $this->createProductReference($brand1, $category, 'Produit A');
        $this->createProductReference($brand2, $category, 'Produit B');

        $payload = $this->decodeJson($this->requestJson('GET', '/api/admin/product-references?brand='.$brand1->getId(), user: $admin));

        self::assertSame(1, $payload['total']);
        self::assertSame('Produit A', $payload['items'][0]['name_fr']);
    }

    public function testListFiltersByCategory(): void
    {
        $admin = $this->createUser('admin-pr-filter-cat@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Cat', 'marque-cat');
        $cat1 = $this->createCategory('Épicerie Cat', 'epicerie-cat');
        $cat2 = $this->createCategory('Boissons Cat', 'boissons-cat');
        $this->createProductReference($brand, $cat1, 'Produit Épicerie');
        $this->createProductReference($brand, $cat2, 'Produit Boisson');

        $payload = $this->decodeJson($this->requestJson('GET', '/api/admin/product-references?category='.$cat1->getId(), user: $admin));

        self::assertSame(1, $payload['total']);
        self::assertSame('Produit Épicerie', $payload['items'][0]['name_fr']);
    }

    public function testListFiltersByStatus(): void
    {
        $admin = $this->createUser('admin-pr-filter-status@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Stat', 'marque-stat');
        $category = $this->createCategory('Catégorie Stat', 'categorie-stat');
        $this->createProductReference($brand, $category, 'Produit Draft');
        $this->createProductReference($brand, $category, 'Produit Approved', status: ProductReferenceStatus::Approved);

        $payload = $this->decodeJson($this->requestJson('GET', '/api/admin/product-references?status=approved', user: $admin));

        self::assertSame(1, $payload['total']);
        self::assertSame('approved', $payload['items'][0]['status']);
    }

    // ── GET ITEM ──────────────────────────────────────────────────────────────

    public function testAdminGetsProductReferenceDetail(): void
    {
        $admin = $this->createUser('admin-pr-detail@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Détail', 'marque-detail');
        $category = $this->createCategory('Catégorie Détail', 'categorie-detail', nameAr: 'تصنيف');
        $ref = $this->createProductReference($brand, $category, 'Jus d\'orange', barcode: '1234567890');

        $response = $this->requestJson('GET', \sprintf('/api/admin/product-references/%s', $ref->getId()), user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($ref->getId()->toRfc4122(), $payload['id']);
        self::assertSame('Jus d\'orange', $payload['name_fr']);
        self::assertSame($brand->getId()->toRfc4122(), $payload['brand_id']);
        self::assertSame($category->getId()->toRfc4122(), $payload['category_id']);
        self::assertSame('تصنيف', $payload['category_name_ar']);
        self::assertSame('1234567890', $payload['barcode']);
        self::assertSame('piece', $payload['unit']);
        self::assertSame('TN', $payload['country']);
        self::assertSame('draft', $payload['status']);
    }

    public function testGetProductReferenceReturns404WhenAbsent(): void
    {
        $admin = $this->createUser('admin-pr-404@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/product-references/00000000-0000-0000-0000-000000000001', user: $admin);

        self::assertSame(404, $response->getStatusCode());
    }

    // ── POST ──────────────────────────────────────────────────────────────────

    public function testAdminCreatesProductReference(): void
    {
        $admin = $this->createUser('admin-pr-create@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Créa', 'marque-crea');
        $category = $this->createCategory('Catégorie Créa', 'categorie-crea');

        $response = $this->requestJson('POST', '/api/admin/product-references', [
            'nameFr' => 'Café moulu',
            'nameAr' => 'قهوة مطحونة',
            'brandId' => $brand->getId()->toRfc4122(),
            'categoryId' => $category->getId()->toRfc4122(),
            'unit' => 'gramme',
            'volume' => '250',
            'barcode' => '9999000001',
            'country' => 'TN',
        ], $admin);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('Café moulu', $payload['name_fr']);
        self::assertSame('قهوة مطحونة', $payload['name_ar']);
        self::assertSame('gramme', $payload['unit']);
        self::assertSame('9999000001', $payload['barcode']);
        self::assertSame('draft', $payload['status']);
        self::assertArrayHasKey('id', $payload);
    }

    public function testAdminCreatesProductReferenceWithExplicitStatus(): void
    {
        $admin = $this->createUser('admin-pr-create-status@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Stat Créa', 'marque-stat-crea');
        $category = $this->createCategory('Catégorie Stat Créa', 'categorie-stat-crea');

        $response = $this->requestJson('POST', '/api/admin/product-references', [
            'nameFr' => 'Produit Approuvé',
            'brandId' => $brand->getId()->toRfc4122(),
            'categoryId' => $category->getId()->toRfc4122(),
            'unit' => 'piece',
            'status' => 'approved',
        ], $admin);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('approved', $this->decodeJson($response)['status']);
    }

    public function testCreateProductReferenceRequiresNameFr(): void
    {
        $admin = $this->createUser('admin-pr-no-name@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque No Name', 'marque-no-name');
        $category = $this->createCategory('Catégorie No Name', 'categorie-no-name');

        $response = $this->requestJson('POST', '/api/admin/product-references', [
            'brandId' => $brand->getId()->toRfc4122(),
            'categoryId' => $category->getId()->toRfc4122(),
            'unit' => 'piece',
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateProductReferenceWithUnknownBrandReturns422(): void
    {
        $admin = $this->createUser('admin-pr-bad-brand@example.test', ['ROLE_ADMIN']);
        $category = $this->createCategory('Catégorie Bad Brand', 'categorie-bad-brand');

        $response = $this->requestJson('POST', '/api/admin/product-references', [
            'nameFr' => 'Test',
            'brandId' => '00000000-0000-0000-0000-000000000001',
            'categoryId' => $category->getId()->toRfc4122(),
            'unit' => 'piece',
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateProductReferenceWithUnknownCategoryReturns422(): void
    {
        $admin = $this->createUser('admin-pr-bad-cat@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Bad Cat', 'marque-bad-cat');

        $response = $this->requestJson('POST', '/api/admin/product-references', [
            'nameFr' => 'Test',
            'brandId' => $brand->getId()->toRfc4122(),
            'categoryId' => '00000000-0000-0000-0000-000000000001',
            'unit' => 'piece',
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateProductReferenceWithDuplicateBarcodeReturns422(): void
    {
        $admin = $this->createUser('admin-pr-dup-barcode@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Dup Bar', 'marque-dup-bar');
        $category = $this->createCategory('Catégorie Dup Bar', 'categorie-dup-bar');
        $this->createProductReference($brand, $category, 'Premier produit', barcode: '8888000001');

        $response = $this->requestJson('POST', '/api/admin/product-references', [
            'nameFr' => 'Deuxième produit',
            'brandId' => $brand->getId()->toRfc4122(),
            'categoryId' => $category->getId()->toRfc4122(),
            'unit' => 'piece',
            'barcode' => '8888000001',
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
    }

    // ── PATCH ─────────────────────────────────────────────────────────────────

    public function testAdminPatchesProductReference(): void
    {
        $admin = $this->createUser('admin-pr-patch@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Patch', 'marque-patch');
        $category = $this->createCategory('Catégorie Patch', 'categorie-patch');
        $ref = $this->createProductReference($brand, $category, 'Produit Initial');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s', $ref->getId()),
            ['nameFr' => 'Produit Modifié'],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('Produit Modifié', $payload['name_fr']);
        self::assertSame($brand->getId()->toRfc4122(), $payload['brand_id']);
    }

    public function testPatchIsPartial(): void
    {
        $admin = $this->createUser('admin-pr-patch-partial@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Part', 'marque-part');
        $category = $this->createCategory('Catégorie Part', 'categorie-part');
        $ref = $this->createProductReference($brand, $category, 'Produit Part', barcode: '7777000001');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s', $ref->getId()),
            ['status' => 'approved'],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('approved', $payload['status']);
        self::assertSame('Produit Part', $payload['name_fr']);
        self::assertSame('7777000001', $payload['barcode']);
    }

    public function testPatchWithNullAliasesClearsArray(): void
    {
        $admin = $this->createUser('admin-pr-patch-aliases@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Alias', 'marque-alias');
        $category = $this->createCategory('Catégorie Alias', 'categorie-alias');
        $ref = $this->createProductReference($brand, $category, 'Produit Alias', aliases: ['alias1', 'alias2']);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s', $ref->getId()),
            ['aliases' => null],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $this->decodeJson($response)['aliases']);
    }

    public function testPatchWithDuplicateBarcodeReturns422(): void
    {
        $admin = $this->createUser('admin-pr-patch-barcode-dup@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Patch Bar', 'marque-patch-bar');
        $category = $this->createCategory('Catégorie Patch Bar', 'categorie-patch-bar');
        $this->createProductReference($brand, $category, 'Produit avec barcode', barcode: '6666000001');
        $ref2 = $this->createProductReference($brand, $category, 'Produit sans barcode');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s', $ref2->getId()),
            ['barcode' => '6666000001'],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testPatchWithNullNameFrReturns422(): void
    {
        $admin = $this->createUser('admin-pr-patch-null-name@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Null Name', 'marque-null-name');
        $category = $this->createCategory('Catégorie Null Name', 'categorie-null-name');
        $ref = $this->createProductReference($brand, $category, 'Produit Name Null');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s', $ref->getId()),
            ['nameFr' => null],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testPatchReturns404WhenAbsent(): void
    {
        $admin = $this->createUser('admin-pr-patch-404@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson(
            'PATCH',
            '/api/admin/product-references/00000000-0000-0000-0000-000000000001',
            ['nameFr' => 'X'],
            $admin,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    // ── ARCHIVE ───────────────────────────────────────────────────────────────

    public function testAdminArchivesProductReference(): void
    {
        $admin = $this->createUser('admin-pr-archive@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Archive', 'marque-archive');
        $category = $this->createCategory('Catégorie Archive', 'categorie-archive');
        $ref = $this->createProductReference($brand, $category, 'Produit à archiver', status: ProductReferenceStatus::Approved);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s/archive', $ref->getId()),
            [],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('archived', $this->decodeJson($response)['status']);
    }

    public function testArchiveReturns404WhenAbsent(): void
    {
        $admin = $this->createUser('admin-pr-archive-404@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson(
            'PATCH',
            '/api/admin/product-references/00000000-0000-0000-0000-000000000001/archive',
            [],
            $admin,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testArchiveIsIdempotent(): void
    {
        $admin = $this->createUser('admin-pr-archive-idempotent@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Idemp', 'marque-idemp');
        $category = $this->createCategory('Catégorie Idemp', 'categorie-idemp');
        $ref = $this->createProductReference($brand, $category, 'Produit déjà archivé', status: ProductReferenceStatus::Archived);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s/archive', $ref->getId()),
            [],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('archived', $this->decodeJson($response)['status']);
    }

    public function testPatchUpdatesBrand(): void
    {
        $admin = $this->createUser('admin-pr-patch-brand@example.test', ['ROLE_ADMIN']);
        $brand1 = $this->createBrand('Marque Init', 'marque-init');
        $brand2 = $this->createBrand('Marque Nouvelle', 'marque-nouvelle');
        $category = $this->createCategory('Catégorie Brand Up', 'categorie-brand-up');
        $ref = $this->createProductReference($brand1, $category, 'Produit Brand');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s', $ref->getId()),
            ['brandId' => $brand2->getId()->toRfc4122()],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($brand2->getId()->toRfc4122(), $this->decodeJson($response)['brand_id']);
    }

    public function testPatchWithUnknownBrandIdReturns422(): void
    {
        $admin = $this->createUser('admin-pr-patch-bad-brand@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Bad Brand', 'marque-bad-brand');
        $category = $this->createCategory('Catégorie Bad Brand Up', 'categorie-bad-brand-up');
        $ref = $this->createProductReference($brand, $category, 'Produit Bad Brand');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s', $ref->getId()),
            ['brandId' => '00000000-0000-0000-0000-000000000001'],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testPatchUpdatesCategory(): void
    {
        $admin = $this->createUser('admin-pr-patch-cat@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Cat Up', 'marque-cat-up');
        $cat1 = $this->createCategory('Catégorie Init', 'categorie-init');
        $cat2 = $this->createCategory('Catégorie Nouvelle', 'categorie-nouvelle');
        $ref = $this->createProductReference($brand, $cat1, 'Produit Cat');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s', $ref->getId()),
            ['categoryId' => $cat2->getId()->toRfc4122()],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($cat2->getId()->toRfc4122(), $this->decodeJson($response)['category_id']);
    }

    public function testPatchWithUnknownCategoryIdReturns422(): void
    {
        $admin = $this->createUser('admin-pr-patch-bad-cat@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Bad Cat Up', 'marque-bad-cat-up');
        $category = $this->createCategory('Catégorie Bad Cat Up', 'categorie-bad-cat-up');
        $ref = $this->createProductReference($brand, $category, 'Produit Bad Cat');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s', $ref->getId()),
            ['categoryId' => '00000000-0000-0000-0000-000000000001'],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testListFilterByInvalidBrandUuidReturns400(): void
    {
        $admin = $this->createUser('admin-pr-filter-bad-brand@example.test', ['ROLE_ADMIN']);

        self::assertSame(400, $this->requestJson('GET', '/api/admin/product-references?brand=not-a-uuid', user: $admin)->getStatusCode());
    }

    public function testListFilterByInvalidCategoryUuidReturns400(): void
    {
        $admin = $this->createUser('admin-pr-filter-bad-cat@example.test', ['ROLE_ADMIN']);

        self::assertSame(400, $this->requestJson('GET', '/api/admin/product-references?category=not-a-uuid', user: $admin)->getStatusCode());
    }

    public function testListFilterByInvalidStatusReturns400(): void
    {
        $admin = $this->createUser('admin-pr-filter-bad-status@example.test', ['ROLE_ADMIN']);

        self::assertSame(400, $this->requestJson('GET', '/api/admin/product-references?status=invalid_value', user: $admin)->getStatusCode());
    }

    public function testPatchCountryNullDefaultsToTN(): void
    {
        $admin = $this->createUser('admin-pr-patch-country-null@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Country Null', 'marque-country-null');
        $category = $this->createCategory('Catégorie Country Null', 'categorie-country-null');
        $ref = $this->createProductReference($brand, $category, 'Produit Country');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s', $ref->getId()),
            ['country' => null],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('TN', $this->decodeJson($response)['country']);
    }

    public function testPatchStatusArchivedReturns422(): void
    {
        $admin = $this->createUser('admin-pr-patch-status-archived@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque Archived Status', 'marque-archived-status');
        $category = $this->createCategory('Catégorie Archived Status', 'categorie-archived-status');
        $ref = $this->createProductReference($brand, $category, 'Produit Status Archived');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s', $ref->getId()),
            ['status' => 'archived'],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    // ── ACCESS CONTROL ────────────────────────────────────────────────────────

    public function testAnonymousIsRejected(): void
    {
        $brand = $this->createBrand('Marque Anon', 'marque-anon');
        $category = $this->createCategory('Catégorie Anon', 'categorie-anon');
        $ref = $this->createProductReference($brand, $category, 'Produit Anon');

        self::assertSame(401, $this->requestJson('GET', '/api/admin/product-references')->getStatusCode());
        self::assertSame(401, $this->requestJson('GET', \sprintf('/api/admin/product-references/%s', $ref->getId()))->getStatusCode());
        self::assertSame(401, $this->requestJson('POST', '/api/admin/product-references', ['nameFr' => 'X'])->getStatusCode());
        self::assertSame(401, $this->requestJson('PATCH', \sprintf('/api/admin/product-references/%s', $ref->getId()), ['nameFr' => 'X'])->getStatusCode());
        self::assertSame(401, $this->requestJson('PATCH', \sprintf('/api/admin/product-references/%s/archive', $ref->getId()), [])->getStatusCode());
    }

    public function testMerchantIsRejected(): void
    {
        $merchant = $this->createUser('merchant-pr@example.test', ['ROLE_MERCHANT']);
        $brand = $this->createBrand('Marque Merchant', 'marque-merchant');
        $category = $this->createCategory('Catégorie Merchant', 'categorie-merchant');
        $ref = $this->createProductReference($brand, $category, 'Produit Merchant');

        self::assertSame(403, $this->requestJson('GET', '/api/admin/product-references', user: $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('GET', \sprintf('/api/admin/product-references/%s', $ref->getId()), user: $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('POST', '/api/admin/product-references', ['nameFr' => 'X'], $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/admin/product-references/%s', $ref->getId()), ['nameFr' => 'X'], $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/admin/product-references/%s/archive', $ref->getId()), [], $merchant)->getStatusCode());
    }

    public function testCustomerIsRejected(): void
    {
        $customer = $this->createUser('customer-pr@example.test', ['ROLE_CUSTOMER']);
        $brand = $this->createBrand('Marque Customer', 'marque-customer');
        $category = $this->createCategory('Catégorie Customer', 'categorie-customer');
        $ref = $this->createProductReference($brand, $category, 'Produit Customer');

        self::assertSame(403, $this->requestJson('GET', '/api/admin/product-references', user: $customer)->getStatusCode());
        self::assertSame(403, $this->requestJson('GET', \sprintf('/api/admin/product-references/%s', $ref->getId()), user: $customer)->getStatusCode());
        self::assertSame(403, $this->requestJson('POST', '/api/admin/product-references', ['nameFr' => 'X'], $customer)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/admin/product-references/%s', $ref->getId()), ['nameFr' => 'X'], $customer)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/admin/product-references/%s/archive', $ref->getId()), [], $customer)->getStatusCode());
    }

    // ── HELPERS ───────────────────────────────────────────────────────────────

    private function createBrand(string $canonicalName, string $slug): Brand
    {
        $brand = (new Brand())
            ->setCanonicalName($canonicalName)
            ->setSlug($slug)
            ->setActive(true);

        $this->entityManager->persist($brand);
        $this->entityManager->flush();

        return $brand;
    }

    private function createCategory(string $nameFr, string $slug, ?string $nameAr = null): Category
    {
        $category = (new Category())
            ->setNameFr($nameFr)
            ->setNameAr($nameAr)
            ->setSlug($slug)
            ->setActive(true);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    /**
     * @param list<string> $aliases
     */
    private function createProductReference(
        Brand $brand,
        Category $category,
        string $nameFr,
        ?string $barcode = null,
        ProductReferenceStatus $status = ProductReferenceStatus::Draft,
        array $aliases = [],
    ): ProductReference {
        $ref = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setUnit(ProductUnit::Piece)
            ->setBarcode($barcode)
            ->setAliases($aliases)
            ->setStatus($status);

        $this->entityManager->persist($ref);
        $this->entityManager->flush();

        return $ref;
    }
}
