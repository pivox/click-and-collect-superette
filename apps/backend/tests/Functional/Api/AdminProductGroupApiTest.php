<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductGroup;
use App\Entity\ProductGroupItem;
use App\Entity\ProductReference;
use App\Enum\ProductGroupItemImportance;
use App\Enum\ProductGroupStatus;
use App\Enum\ProductGroupVisibility;
use App\Enum\ProductUnit;

final class AdminProductGroupApiTest extends FunctionalApiTestCase
{
    public function testAdminAccessIsRequired(): void
    {
        $merchant = $this->createUser('merchant-product-groups@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('GET', '/api/admin/product-groups', user: $merchant);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAdminCreatesDraftProductGroupWithGeneratedSlug(): void
    {
        $admin = $this->createUser('admin-product-group-create@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/product-groups', [
            'nameFr' => 'Premières nécessités',
            'nameAr' => 'مواد أساسية',
            'descriptionFr' => 'Produits du quotidien',
            'visibility' => 'merchant',
            'icon' => 'shopping-basket',
            'sortOrder' => 3,
        ], $admin);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame('Premières nécessités', $payload['name_fr']);
        self::assertSame('premieres-necessites', $payload['slug']);
        self::assertSame('draft', $payload['status']);
        self::assertSame('merchant', $payload['visibility']);
        self::assertSame('TN', $payload['market_country']);
        self::assertSame(0, $payload['items_count']);
    }

    public function testCreateRequiresNameFr(): void
    {
        $admin = $this->createUser('admin-product-group-name-required@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/product-groups', [
            'nameFr' => '  ',
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('ADMIN_PRODUCT_GROUP_NAME_FR_BLANK', (string) $response->getContent());
    }

    public function testCreateRejectsDuplicateSlug(): void
    {
        $admin = $this->createUser('admin-product-group-duplicate-slug@example.test', ['ROLE_ADMIN']);
        $this->persistGroup('Petit déjeuner', 'petit-dejeuner');

        $response = $this->requestJson('POST', '/api/admin/product-groups', [
            'nameFr' => 'Autre nom',
            'slug' => 'petit-dejeuner',
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('ADMIN_PRODUCT_GROUP_SLUG_DUPLICATE', (string) $response->getContent());
    }

    public function testAdminListsProductGroupsWithPaginationAndFilters(): void
    {
        $admin = $this->createUser('admin-product-group-list@example.test', ['ROLE_ADMIN']);
        $this->persistGroup('Boissons froides', 'boissons-froides', status: ProductGroupStatus::Published, sortOrder: 20);
        $this->persistGroup('Épicerie sèche', 'epicerie-seche', status: ProductGroupStatus::Draft, sortOrder: 10);

        $payload = $this->decodeJson(
            $this->requestJson('GET', '/api/admin/product-groups?q=boissons&status=published&market_country=TN&page=1&limit=10', user: $admin),
        );

        self::assertSame(1, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(10, $payload['limit']);
        self::assertSame('Boissons froides', $payload['items'][0]['name_fr']);
        self::assertSame('published', $payload['items'][0]['status']);
    }

    public function testAdminGetsProductGroupDetailWithSortedItems(): void
    {
        $admin = $this->createUser('admin-product-group-detail@example.test', ['ROLE_ADMIN']);
        [$milk, $water] = $this->persistReferences(['Lait', 'Eau']);
        $group = $this->persistGroup('Premières nécessités', 'premieres-necessites');
        $this->persistItem($group, $water, sortOrder: 20, importance: ProductGroupItemImportance::Optional);
        $this->persistItem($group, $milk, sortOrder: 10, importance: ProductGroupItemImportance::Required);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/admin/product-groups/%s', $group->getId()), user: $admin);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame($group->getId()->toRfc4122(), $payload['id']);
        self::assertCount(2, $payload['items']);
        self::assertSame($milk->getId()->toRfc4122(), $payload['items'][0]['product_reference']['id']);
        self::assertSame('required', $payload['items'][0]['importance']);
        self::assertSame($water->getId()->toRfc4122(), $payload['items'][1]['product_reference']['id']);
    }

    public function testAdminPatchesProductGroupMetadata(): void
    {
        $admin = $this->createUser('admin-product-group-patch@example.test', ['ROLE_ADMIN']);
        $group = $this->persistGroup('Ancien nom', 'ancien-nom');

        $response = $this->requestJson('PATCH', \sprintf('/api/admin/product-groups/%s', $group->getId()), [
            'nameFr' => 'Nouveau nom',
            'descriptionAr' => 'وصف',
            'sortOrder' => 15,
        ], $admin);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame('Nouveau nom', $payload['name_fr']);
        self::assertSame('وصف', $payload['description_ar']);
        self::assertSame(15, $payload['sort_order']);
    }

    public function testPublishEmptyGroupIsAllowed(): void
    {
        $admin = $this->createUser('admin-product-group-publish@example.test', ['ROLE_ADMIN']);
        $group = $this->persistGroup('Vide publiable', 'vide-publiable');

        $response = $this->requestJson('PATCH', \sprintf('/api/admin/product-groups/%s/publish', $group->getId()), [], $admin);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('published', $this->decodeJson($response)['status']);
    }

    public function testArchiveGroup(): void
    {
        $admin = $this->createUser('admin-product-group-archive@example.test', ['ROLE_ADMIN']);
        $group = $this->persistGroup('À archiver', 'a-archiver', status: ProductGroupStatus::Published);

        $response = $this->requestJson('PATCH', \sprintf('/api/admin/product-groups/%s/archive', $group->getId()), [], $admin);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('archived', $this->decodeJson($response)['status']);
    }

    public function testAdminAddsPatchesAndDeletesProductGroupItem(): void
    {
        $admin = $this->createUser('admin-product-group-items@example.test', ['ROLE_ADMIN']);
        [$milk] = $this->persistReferences(['Lait']);
        $group = $this->persistGroup('Petit déjeuner', 'petit-dejeuner');

        $addResponse = $this->requestJson('POST', \sprintf('/api/admin/product-groups/%s/items', $group->getId()), [
            'productReferenceId' => $milk->getId()->toRfc4122(),
            'sortOrder' => 7,
            'importance' => 'recommended',
        ], $admin);

        self::assertSame(201, $addResponse->getStatusCode(), (string) $addResponse->getContent());
        $added = $this->decodeJson($addResponse);
        self::assertSame($milk->getId()->toRfc4122(), $added['product_reference']['id']);
        self::assertSame(7, $added['sort_order']);

        $patchResponse = $this->requestJson('PATCH', \sprintf('/api/admin/product-groups/%s/items/%s', $group->getId(), $added['id']), [
            'sortOrder' => 2,
            'importance' => 'required',
        ], $admin);

        self::assertSame(200, $patchResponse->getStatusCode(), (string) $patchResponse->getContent());
        $patched = $this->decodeJson($patchResponse);
        self::assertSame(2, $patched['sort_order']);
        self::assertSame('required', $patched['importance']);

        $deleteResponse = $this->requestJson('DELETE', \sprintf('/api/admin/product-groups/%s/items/%s', $group->getId(), $added['id']), user: $admin);

        self::assertSame(204, $deleteResponse->getStatusCode(), (string) $deleteResponse->getContent());
    }

    public function testAddingDuplicateProductGroupItemReturns422(): void
    {
        $admin = $this->createUser('admin-product-group-item-duplicate@example.test', ['ROLE_ADMIN']);
        [$milk] = $this->persistReferences(['Lait']);
        $group = $this->persistGroup('Petit déjeuner', 'petit-dejeuner');
        $this->persistItem($group, $milk);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', \sprintf('/api/admin/product-groups/%s/items', $group->getId()), [
            'productReferenceId' => $milk->getId()->toRfc4122(),
            'importance' => 'optional',
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('ADMIN_PRODUCT_GROUP_ITEM_DUPLICATE', (string) $response->getContent());
    }

    public function testAddingUnknownProductReferenceReturns422(): void
    {
        $admin = $this->createUser('admin-product-group-item-missing-ref@example.test', ['ROLE_ADMIN']);
        $group = $this->persistGroup('Petit déjeuner', 'petit-dejeuner');

        $response = $this->requestJson('POST', \sprintf('/api/admin/product-groups/%s/items', $group->getId()), [
            'productReferenceId' => '550e8400-e29b-41d4-a716-446655440000',
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('ADMIN_PRODUCT_GROUP_PRODUCT_REFERENCE_NOT_FOUND', (string) $response->getContent());
    }

    private function persistGroup(
        string $nameFr,
        string $slug,
        ProductGroupStatus $status = ProductGroupStatus::Draft,
        int $sortOrder = 0,
    ): ProductGroup {
        $group = (new ProductGroup())
            ->setNameFr($nameFr)
            ->setSlug($slug)
            ->setStatus($status)
            ->setVisibility(ProductGroupVisibility::Merchant)
            ->setSortOrder($sortOrder);

        $this->entityManager->persist($group);
        $this->entityManager->flush();

        return $group;
    }

    /**
     * @param list<string> $names
     *
     * @return list<ProductReference>
     */
    private function persistReferences(array $names): array
    {
        $brand = (new Brand())->setCanonicalName('Group API Brand '.uniqid())->setSlug('group-api-brand-'.uniqid());
        $category = (new Category())->setNameFr('Group API Category '.uniqid())->setSlug('group-api-category-'.uniqid());
        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);

        $references = [];
        foreach ($names as $name) {
            $reference = (new ProductReference())
                ->setBrand($brand)
                ->setCategory($category)
                ->setNameFr($name)
                ->setUnit(ProductUnit::Piece);
            $this->entityManager->persist($reference);
            $references[] = $reference;
        }
        $this->entityManager->flush();

        return $references;
    }

    private function persistItem(
        ProductGroup $group,
        ProductReference $productReference,
        int $sortOrder = 0,
        ProductGroupItemImportance $importance = ProductGroupItemImportance::Recommended,
    ): ProductGroupItem {
        $item = (new ProductGroupItem())
            ->setProductReference($productReference)
            ->setSortOrder($sortOrder)
            ->setImportance($importance);
        $group->addItem($item);
        $this->entityManager->persist($item);

        return $item;
    }
}
