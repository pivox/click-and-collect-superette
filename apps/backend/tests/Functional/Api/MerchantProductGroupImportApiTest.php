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
use App\Enum\ProductGroupStatus;
use App\Enum\ProductGroupVisibility;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Uuid;

final class MerchantProductGroupImportApiTest extends FunctionalApiTestCase
{
    public function testFirstImportCreatesMissingProductsAndRequiresPriceCompletion(): void
    {
        $merchant = $this->createUser('merchant-product-group-import-create@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $milk = $this->createProductReference('Lait UHT');
        $water = $this->createProductReference('Eau 1.5L');
        $group = $this->createGroup('Premières nécessités', 'import-create-necessites', ProductGroupStatus::Published, ProductGroupVisibility::Merchant);
        $this->addItem($group, $milk);
        $this->addItem($group, $water);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', $this->importPath($shop), [
            'groupId' => $group->getId()->toRfc4122(),
            'selectedProductReferenceIds' => [
                $milk->getId()->toRfc4122(),
                $water->getId()->toRfc4122(),
            ],
            'skipExisting' => true,
            'defaultVisibility' => true,
            'defaultAvailability' => false,
        ], user: $merchant);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([
            'created' => 2,
            'alreadyInCatalog' => 0,
            'skipped' => 0,
            'requiresPriceCompletion' => 2,
            'errors' => [],
        ], $this->decodeJson($response));

        $createdProducts = $this->entityManager->getRepository(MerchantProduct::class)->findBy(['shop' => $shop]);
        self::assertCount(2, $createdProducts);
        foreach ($createdProducts as $createdProduct) {
            self::assertSame('0.000', $createdProduct->getPriceTnd());
            self::assertFalse($createdProduct->isVisible());
            self::assertFalse($createdProduct->isAvailable());
        }
    }

    public function testSecondImportAndManuallyExistingProductsCreateNoDuplicate(): void
    {
        $merchant = $this->createUser('merchant-product-group-import-duplicates@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $otherMerchant = $this->createUser('merchant-product-group-import-duplicates-other@example.test', ['ROLE_MERCHANT']);
        $otherShop = $this->createShop($otherMerchant);
        $sharedReference = $this->createProductReference('Couscous fin');
        $manualReference = $this->createProductReference('Harissa tube');
        $group = $this->createGroup('Épicerie sèche', 'import-duplicates-epicerie', ProductGroupStatus::Published, ProductGroupVisibility::Merchant);
        $otherGroup = $this->createGroup('Autre groupement', 'import-duplicates-other-group', ProductGroupStatus::Published, ProductGroupVisibility::Merchant);
        $this->addItem($group, $sharedReference);
        $this->addItem($group, $manualReference);
        $this->addItem($otherGroup, $sharedReference);
        $this->createMerchantProduct($shop, $manualReference);
        $this->createMerchantProduct($otherShop, $sharedReference);
        $this->entityManager->flush();

        $firstResponse = $this->requestJson('POST', $this->importPath($shop), [
            'groupId' => $group->getId()->toRfc4122(),
            'selectedProductReferenceIds' => [
                $sharedReference->getId()->toRfc4122(),
                $manualReference->getId()->toRfc4122(),
            ],
            'skipExisting' => true,
            'defaultAvailability' => true,
        ], user: $merchant);
        self::assertSame(200, $firstResponse->getStatusCode(), (string) $firstResponse->getContent());
        self::assertSame(1, $this->decodeJson($firstResponse)['created']);
        self::assertSame(1, $this->decodeJson($firstResponse)['alreadyInCatalog']);

        $secondResponse = $this->requestJson('POST', $this->importPath($shop), [
            'groupId' => $otherGroup->getId()->toRfc4122(),
            'selectedProductReferenceIds' => [$sharedReference->getId()->toRfc4122()],
            'skipExisting' => true,
        ], user: $merchant);

        self::assertSame(200, $secondResponse->getStatusCode(), (string) $secondResponse->getContent());
        self::assertSame([
            'created' => 0,
            'alreadyInCatalog' => 1,
            'skipped' => 0,
            'requiresPriceCompletion' => 0,
            'errors' => [],
        ], $this->decodeJson($secondResponse));
        self::assertCount(2, $this->entityManager->getRepository(MerchantProduct::class)->findBy(['shop' => $shop]));
    }

    public function testOutsideGroupAndNonApprovedReferencesAreSkippedWithErrors(): void
    {
        $merchant = $this->createUser('merchant-product-group-import-errors@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $approved = $this->createProductReference('Sucre poudre');
        $outside = $this->createProductReference('Produit hors groupement');
        $draft = $this->createProductReference('Produit brouillon', ProductReferenceStatus::Draft);
        $group = $this->createGroup('Épicerie', 'import-errors-epicerie', ProductGroupStatus::Published, ProductGroupVisibility::Merchant);
        $this->addItem($group, $approved);
        $this->addItem($group, $draft);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', $this->importPath($shop), [
            'groupId' => $group->getId()->toRfc4122(),
            'selectedProductReferenceIds' => [
                $approved->getId()->toRfc4122(),
                $outside->getId()->toRfc4122(),
                $draft->getId()->toRfc4122(),
            ],
        ], user: $merchant);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['created']);
        self::assertSame(0, $payload['alreadyInCatalog']);
        self::assertSame(2, $payload['skipped']);
        self::assertSame(1, $payload['requiresPriceCompletion']);
        self::assertSame([
            [
                'productReferenceId' => $outside->getId()->toRfc4122(),
                'code' => 'PRODUCT_REFERENCE_NOT_IN_GROUP',
                'message' => 'Selected product reference does not belong to the product group.',
            ],
            [
                'productReferenceId' => $draft->getId()->toRfc4122(),
                'code' => 'PRODUCT_REFERENCE_NOT_APPROVED',
                'message' => 'Selected product reference is not approved for merchant import.',
            ],
        ], $payload['errors']);
    }

    public function testUnpublishedAdminOnlyAndUnknownGroupReturn404(): void
    {
        $merchant = $this->createUser('merchant-product-group-import-not-found@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $draft = $this->createGroup('Brouillon', 'import-draft-not-found', ProductGroupStatus::Draft, ProductGroupVisibility::Merchant);
        $archived = $this->createGroup('Archivé', 'import-archived-not-found', ProductGroupStatus::Archived, ProductGroupVisibility::Merchant);
        $adminOnly = $this->createGroup('Admin only', 'import-admin-only-not-found', ProductGroupStatus::Published, ProductGroupVisibility::AdminOnly);

        foreach ([$draft->getId()->toRfc4122(), $archived->getId()->toRfc4122(), $adminOnly->getId()->toRfc4122(), Uuid::v4()->toRfc4122()] as $groupId) {
            $response = $this->requestJson('POST', $this->importPath($shop), [
                'groupId' => $groupId,
                'selectedProductReferenceIds' => [Uuid::v4()->toRfc4122()],
            ], user: $merchant);

            self::assertSame(404, $response->getStatusCode(), (string) $response->getContent());
        }
    }

    public function testNonOwnerAdminClientAndAnonymousAreRejected(): void
    {
        $owner = $this->createUser('merchant-product-group-import-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);
        $group = $this->createGroup('Boissons', 'import-access-boissons', ProductGroupStatus::Published, ProductGroupVisibility::Merchant);
        $otherMerchant = $this->createUser('merchant-product-group-import-other@example.test', ['ROLE_MERCHANT']);
        $admin = $this->createUser('merchant-product-group-import-admin@example.test', ['ROLE_ADMIN']);
        $client = $this->createUser('merchant-product-group-import-client@example.test', ['ROLE_CUSTOMER']);
        $payload = [
            'groupId' => $group->getId()->toRfc4122(),
            'selectedProductReferenceIds' => [Uuid::v4()->toRfc4122()],
        ];

        foreach ([$otherMerchant, $admin, $client] as $user) {
            self::assertSame(403, $this->requestJson('POST', $this->importPath($shop), $payload, user: $user)->getStatusCode());
        }

        self::assertContains($this->requestJson('POST', $this->importPath($shop), $payload)->getStatusCode(), [401, 403]);
    }

    public function testRouteListIncludesImportPostContract(): void
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

    private function importPath(Shop $shop): string
    {
        return \sprintf('/api/merchant/stores/%s/catalog/import-from-product-group', $shop->getId());
    }

    private function createGroup(
        string $nameFr,
        string $slug,
        ProductGroupStatus $status,
        ProductGroupVisibility $visibility,
    ): ProductGroup {
        $group = (new ProductGroup())
            ->setNameFr($nameFr)
            ->setSlug($slug)
            ->setStatus($status)
            ->setVisibility($visibility);

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
            ->setCanonicalName('Import Brand '.$suffix)
            ->setSlug('import-brand-'.$suffix);
        $category = (new Category())
            ->setNameFr('Import Category '.$suffix)
            ->setSlug('import-category-'.$suffix);
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

    private function addItem(ProductGroup $group, ProductReference $reference): ProductGroupItem
    {
        $item = (new ProductGroupItem())->setProductReference($reference);
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
