<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\ProductGroup;
use App\Entity\ProductGroupItem;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductGroupItemImportance;
use App\Enum\ProductGroupStatus;
use App\Enum\ProductGroupVisibility;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Uuid;

final class MerchantProductGroupApiTest extends FunctionalApiTestCase
{
    public function testOwnerMerchantListsPublishedMerchantProductGroupsOnly(): void
    {
        $merchant = $this->createUser('merchant-product-groups-list@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $this->createGroup('Z Groupement brouillon', 'draft-group', ProductGroupStatus::Draft, ProductGroupVisibility::Merchant, 1);
        $this->createGroup('A Admin only', 'admin-only', ProductGroupStatus::Published, ProductGroupVisibility::AdminOnly, 2);
        $this->createGroup('B Archivé', 'archived-group', ProductGroupStatus::Archived, ProductGroupVisibility::Merchant, 3);
        $breakfast = $this->createGroup('Petit déjeuner', 'petit-dejeuner', ProductGroupStatus::Published, ProductGroupVisibility::Merchant, 20);
        $essentials = $this->createGroup('Premières nécessités', 'premieres-necessites', ProductGroupStatus::Published, ProductGroupVisibility::Merchant, 10);
        $this->addItem($essentials, $this->createProductReference('Sel'));

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/product-groups', $shop->getId()), user: $merchant);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['id']);
        self::assertCount(2, $payload['items']);
        self::assertSame($essentials->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('Premières nécessités', $payload['items'][0]['name_fr']);
        self::assertSame(1, $payload['items'][0]['items_count']);
        self::assertSame($breakfast->getId()->toRfc4122(), $payload['items'][1]['id']);
    }

    public function testOwnerMerchantGetsProductGroupDetailWithLineStatusesForStore(): void
    {
        $merchant = $this->createUser('merchant-product-groups-detail@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $newReference = $this->createProductReference('Lait UHT');
        $existingReference = $this->createProductReference('Eau 1.5L');
        $draftReference = $this->createProductReference('Produit brouillon', ProductReferenceStatus::Draft);
        $group = $this->createGroup('Premières nécessités', 'necessites-status', ProductGroupStatus::Published, ProductGroupVisibility::Merchant);
        $this->addItem($group, $existingReference, 20, ProductGroupItemImportance::Optional);
        $this->addItem($group, $draftReference, 30, ProductGroupItemImportance::Recommended);
        $this->addItem($group, $newReference, 10, ProductGroupItemImportance::Required);
        $this->createMerchantProduct($shop, $existingReference);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/product-groups/%s', $shop->getId(), $group->getId()), user: $merchant);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame($group->getId()->toRfc4122(), $payload['id']);
        self::assertCount(3, $payload['items']);
        self::assertSame($newReference->getId()->toRfc4122(), $payload['items'][0]['product_reference']['id']);
        self::assertSame('new', $payload['items'][0]['status']);
        self::assertSame('required', $payload['items'][0]['importance']);
        self::assertSame($existingReference->getId()->toRfc4122(), $payload['items'][1]['product_reference']['id']);
        self::assertSame('already_present', $payload['items'][1]['status']);
        self::assertSame($draftReference->getId()->toRfc4122(), $payload['items'][2]['product_reference']['id']);
        self::assertSame('non_importable', $payload['items'][2]['status']);
    }

    public function testLineAlreadyPresentIsStoreScoped(): void
    {
        $merchant = $this->createUser('merchant-product-groups-store-scope@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $otherMerchant = $this->createUser('merchant-product-groups-other-store@example.test', ['ROLE_MERCHANT']);
        $otherShop = $this->createShop($otherMerchant);
        $reference = $this->createProductReference('Couscous fin');
        $group = $this->createGroup('Épicerie sèche', 'epicerie-seche-store-scope', ProductGroupStatus::Published, ProductGroupVisibility::Merchant);
        $this->addItem($group, $reference);
        $this->createMerchantProduct($otherShop, $reference);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/product-groups/%s', $shop->getId(), $group->getId()), user: $merchant);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('new', $this->decodeJson($response)['items'][0]['status']);
    }

    public function testNonOwnerAdminClientAndAnonymousAreRejected(): void
    {
        $owner = $this->createUser('merchant-product-groups-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);
        $group = $this->createGroup('Boissons froides', 'boissons-froides-access', ProductGroupStatus::Published, ProductGroupVisibility::Merchant);
        $otherMerchant = $this->createUser('merchant-product-groups-other@example.test', ['ROLE_MERCHANT']);
        $admin = $this->createUser('merchant-product-groups-admin@example.test', ['ROLE_ADMIN']);
        $client = $this->createUser('merchant-product-groups-client@example.test', ['ROLE_CUSTOMER']);

        $listPath = \sprintf('/api/merchant/stores/%s/product-groups', $shop->getId());
        $detailPath = \sprintf('/api/merchant/stores/%s/product-groups/%s', $shop->getId(), $group->getId());

        foreach ([$otherMerchant, $admin, $client] as $user) {
            self::assertSame(403, $this->requestJson('GET', $listPath, user: $user)->getStatusCode());
            self::assertSame(403, $this->requestJson('GET', $detailPath, user: $user)->getStatusCode());
        }

        self::assertContains($this->requestJson('GET', $listPath)->getStatusCode(), [401, 403]);
        self::assertContains($this->requestJson('GET', $detailPath)->getStatusCode(), [401, 403]);
    }

    public function testInactiveMerchantIsRejected(): void
    {
        $merchant = $this->createUser('merchant-product-groups-inactive@example.test', ['ROLE_MERCHANT']);
        $merchant->setActive(false);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/product-groups', $shop->getId()), user: $merchant);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('MERCHANT_ACCOUNT_INACTIVE', (string) $response->getContent());
    }

    public function testUnpublishedOrUnknownGroupReturns404(): void
    {
        $merchant = $this->createUser('merchant-product-groups-not-found@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $draft = $this->createGroup('Brouillon', 'merchant-draft-not-found', ProductGroupStatus::Draft, ProductGroupVisibility::Merchant);
        $adminOnly = $this->createGroup('Admin only', 'merchant-admin-only-not-found', ProductGroupStatus::Published, ProductGroupVisibility::AdminOnly);

        self::assertSame(404, $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/product-groups/%s', $shop->getId(), $draft->getId()), user: $merchant)->getStatusCode());
        self::assertSame(404, $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/product-groups/%s', $shop->getId(), $adminOnly->getId()), user: $merchant)->getStatusCode());
        self::assertSame(404, $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/product-groups/%s', $shop->getId(), Uuid::v4()->toRfc4122()), user: $merchant)->getStatusCode());
    }

    public function testMerchantProductGroupRoutesExposeStoreScopedReadAndImportContracts(): void
    {
        $router = self::getContainer()->get(RouterInterface::class);
        $routes = [];

        foreach ($router->getRouteCollection() as $route) {
            if (str_contains($route->getPath(), 'product-groups') || str_contains($route->getPath(), 'import-from-product-group')) {
                $routes[] = implode(' ', $route->getMethods()).' '.$route->getPath();
            }
        }

        self::assertContains('GET /api/merchant/stores/{storeId}/product-groups', $routes);
        self::assertContains('GET /api/merchant/stores/{storeId}/product-groups/{groupId}', $routes);
        self::assertContains('POST /api/merchant/stores/{storeId}/catalog/import-from-product-group', $routes);
    }

    private function createGroup(
        string $nameFr,
        string $slug,
        ProductGroupStatus $status,
        ProductGroupVisibility $visibility,
        int $sortOrder = 0,
    ): ProductGroup {
        $group = (new ProductGroup())
            ->setNameFr($nameFr)
            ->setSlug($slug)
            ->setStatus($status)
            ->setVisibility($visibility)
            ->setSortOrder($sortOrder);

        $this->entityManager->persist($group);
        $this->entityManager->flush();

        return $group;
    }

    private function createProductReference(
        string $nameFr,
        ProductReferenceStatus $status = ProductReferenceStatus::Approved,
    ): ProductReference {
        $suffix = substr(str_replace('-', '', Uuid::v4()->toRfc4122()), 0, 12);
        $brand = (new Brand())
            ->setCanonicalName('Group Brand '.$suffix)
            ->setSlug('group-brand-'.$suffix);
        $category = (new Category())
            ->setNameFr('Group Category '.$suffix)
            ->setSlug('group-category-'.$suffix);
        $reference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setVolume('1.000')
            ->setUnit(ProductUnit::Piece)
            ->setStatus($status);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($reference);
        $this->entityManager->flush();

        return $reference;
    }

    private function addItem(
        ProductGroup $group,
        ProductReference $reference,
        int $sortOrder = 0,
        ProductGroupItemImportance $importance = ProductGroupItemImportance::Recommended,
    ): ProductGroupItem {
        $item = (new ProductGroupItem())
            ->setProductReference($reference)
            ->setSortOrder($sortOrder)
            ->setImportance($importance);
        $group->addItem($item);
        $this->entityManager->persist($item);

        return $item;
    }

    private function createMerchantProduct(Shop $shop, ProductReference $reference): MerchantProduct
    {
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($reference)
            ->setPriceTnd('1.500')
            ->setAvailable(true)
            ->setVisible(false);

        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }
}
