<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Symfony\Component\Routing\RouterInterface;

final class MerchantCatalogApiTest extends FunctionalApiTestCase
{
    public function testOwnerMerchantCanReadCatalog(): void
    {
        $merchant = $this->createUser('merchant-catalog-reader@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Vitalait', 'Lait & produits laitiers', 'Lait demi-écrémé');
        $merchantProduct = $this->createMerchantProduct($shop, $productReference);
        self::assertSame(1, $this->entityManager->getRepository(MerchantProduct::class)->count([]));
        self::assertCount(1, $this->entityManager->getRepository(MerchantProduct::class)->findCatalogForShop($shop));

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload);
        self::assertSame($merchantProduct->getId()->toRfc4122(), $payload[0]['id']);
        self::assertSame($productReference->getId()->toRfc4122(), $payload[0]['product_reference_id']);
        self::assertSame('Lait demi-écrémé', $payload[0]['name_fr']);
        self::assertSame('Vitalait', $payload[0]['brand']);
        self::assertSame('Lait & produits laitiers', $payload[0]['category']);
        self::assertSame('1.000', $payload[0]['volume']);
        self::assertSame('litre', $payload[0]['unit']);
        self::assertSame('1.500', $payload[0]['price_tnd']);
        self::assertTrue($payload[0]['is_available']);
        self::assertTrue($payload[0]['is_visible']);
        self::assertNull($payload[0]['merchant_note']);
    }

    public function testOwnerMerchantCanManageCatalog(): void
    {
        $merchant = $this->createUser('merchant-catalog-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Vitalait', 'Lait & produits laitiers', 'Lait demi-écrémé');

        $postResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()),
            [
                'product_reference_id' => $productReference->getId()->toRfc4122(),
                'price_tnd' => '1.650',
                'is_available' => true,
                'is_visible' => true,
                'merchant_note' => null,
            ],
            $merchant,
        );

        self::assertSame(201, $postResponse->getStatusCode());
        $createdMerchantProduct = $this->entityManager->getRepository(MerchantProduct::class)->findOneForShopAndProductReference($shop, $productReference);
        self::assertInstanceOf(MerchantProduct::class, $createdMerchantProduct);
        self::assertSame('1.650', $createdMerchantProduct->getPriceTnd());
        self::assertTrue($createdMerchantProduct->isAvailable());
        self::assertTrue($createdMerchantProduct->isVisible());
        self::assertNull($createdMerchantProduct->getMerchantNote());
        $patchResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $createdMerchantProduct->getId()),
            [
                'price_tnd' => '1.700',
                'is_available' => false,
                'is_visible' => true,
                'merchant_note' => 'Rupture fréquente',
            ],
            $merchant,
        );

        self::assertSame(200, $patchResponse->getStatusCode());
        $updatedMerchantProduct = $this->entityManager->getRepository(MerchantProduct::class)->find($createdMerchantProduct->getId());
        self::assertInstanceOf(MerchantProduct::class, $updatedMerchantProduct);
        self::assertSame('1.700', $updatedMerchantProduct->getPriceTnd());
        self::assertFalse($updatedMerchantProduct->isAvailable());
        self::assertTrue($updatedMerchantProduct->isVisible());
        self::assertSame('Rupture fréquente', $updatedMerchantProduct->getMerchantNote());

        $deleteResponse = $this->requestJson('DELETE', \sprintf('/api/merchant/catalog/%s', $createdMerchantProduct->getId()), user: $merchant);
        self::assertSame(204, $deleteResponse->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(MerchantProduct::class)->count(['shop' => $shop]));
    }

    public function testNonOwnerMerchantIsDeniedOnCatalogRoutes(): void
    {
        $owner = $this->createUser('merchant-catalog-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-catalog-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);
        $productReference = $this->createProductReference('Délice', 'Yaourts', 'Yaourt nature');
        $merchantProduct = $this->createMerchantProduct($shop, $productReference);

        $getResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $otherMerchant);
        $postResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()),
            $this->validCatalogCreatePayload($productReference),
            $otherMerchant,
        );
        $patchResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()),
            ['price_tnd' => '2.000'],
            $otherMerchant,
        );
        $deleteResponse = $this->requestJson('DELETE', \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()), user: $otherMerchant);

        self::assertSame(403, $getResponse->getStatusCode());
        self::assertSame(403, $postResponse->getStatusCode());
        self::assertSame(403, $patchResponse->getStatusCode());
        self::assertSame(403, $deleteResponse->getStatusCode());
    }

    public function testSimpleClientAndAnonymousUserAreDeniedOnCatalogRoutes(): void
    {
        $merchant = $this->createUser('merchant-catalog-owner@example.test', ['ROLE_MERCHANT']);
        $client = $this->createUser('client-catalog@example.test');
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Safia', 'Eaux', 'Eau minérale');
        $merchantProduct = $this->createMerchantProduct($shop, $productReference);

        $clientGetResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $client);
        $clientPostResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()),
            $this->validCatalogCreatePayload($productReference),
            $client,
        );
        $clientPatchResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()),
            ['is_available' => false],
            $client,
        );
        $clientDeleteResponse = $this->requestJson('DELETE', \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()), user: $client);

        self::assertSame(403, $clientGetResponse->getStatusCode());
        self::assertSame(403, $clientPostResponse->getStatusCode());
        self::assertSame(403, $clientPatchResponse->getStatusCode());
        self::assertSame(403, $clientDeleteResponse->getStatusCode());

        $anonymousGetResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()));
        $anonymousPostResponse = $this->requestJson('POST', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), $this->validCatalogCreatePayload($productReference));
        $anonymousPatchResponse = $this->requestJson('PATCH', \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()), ['is_available' => false]);
        $anonymousDeleteResponse = $this->requestJson('DELETE', \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()));

        self::assertContains($anonymousGetResponse->getStatusCode(), [401, 403]);
        self::assertContains($anonymousPostResponse->getStatusCode(), [401, 403]);
        self::assertContains($anonymousPatchResponse->getStatusCode(), [401, 403]);
        self::assertContains($anonymousDeleteResponse->getStatusCode(), [401, 403]);
    }

    public function testAdminWithoutMerchantRoleIsDeniedOnMerchantCatalogRoutes(): void
    {
        $admin = $this->createUser('admin-catalog@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-catalog-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Safia', 'Eaux', 'Eau admin');
        $merchantProduct = $this->createMerchantProduct($shop, $productReference);

        $getResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $admin);
        $postResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()),
            $this->validCatalogCreatePayload($productReference),
            $admin,
        );
        $patchResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()),
            ['is_available' => false],
            $admin,
        );
        $deleteResponse = $this->requestJson('DELETE', \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()), user: $admin);

        self::assertSame(403, $getResponse->getStatusCode());
        self::assertSame(403, $postResponse->getStatusCode());
        self::assertSame(403, $patchResponse->getStatusCode());
        self::assertSame(403, $deleteResponse->getStatusCode());
        self::assertSame(1, $this->entityManager->getRepository(MerchantProduct::class)->count(['shop' => $shop]));
    }

    public function testCatalogRoutesExposeOnlyMerchantCatalogContract(): void
    {
        $router = self::getContainer()->get(RouterInterface::class);
        $catalogRoutes = [];

        foreach ($router->getRouteCollection() as $route) {
            if (str_contains($route->getPath(), 'catalog')) {
                $catalogRoutes[] = implode(' ', $route->getMethods()).' '.$route->getPath();
            }
        }

        sort($catalogRoutes);

        self::assertSame([
            'DELETE /api/merchant/catalog/{merchantProductId}',
            'GET /api/merchant/stores/{storeId}/catalog',
            'GET /api/stores/{storeId}/catalog',
            'PATCH /api/merchant/catalog/{merchantProductId}',
            'POST /api/merchant/stores/{storeId}/catalog',
        ], $catalogRoutes);
    }

    public function testDuplicateProductReferenceInSameShopReturnsConflict(): void
    {
        $merchant = $this->createUser('merchant-catalog-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Randa', 'Pâtes', 'Spaghetti');

        $firstResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()),
            $this->validCatalogCreatePayload($productReference),
            $merchant,
        );
        $duplicateResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()),
            $this->validCatalogCreatePayload($productReference),
            $merchant,
        );

        self::assertSame(201, $firstResponse->getStatusCode());
        self::assertSame(409, $duplicateResponse->getStatusCode());
        self::assertSame(1, $this->entityManager->getRepository(MerchantProduct::class)->count(['shop' => $shop]));
    }

    public function testCatalogCreateRejectsZeroPrice(): void
    {
        $merchant = $this->createUser('merchant-catalog-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Vitalait', 'Lait & produits laitiers', 'Lait zéro');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()),
            $this->validCatalogCreatePayload($productReference, ['price_tnd' => '0.000']),
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(MerchantProduct::class)->count(['shop' => $shop]));
    }

    public function testCatalogCreateRejectsPriceTooLargeForDatabasePrecision(): void
    {
        $merchant = $this->createUser('merchant-catalog-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Vitalait', 'Lait & produits laitiers', 'Lait trop cher');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()),
            $this->validCatalogCreatePayload($productReference, ['price_tnd' => '99999999.999']),
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(MerchantProduct::class)->count(['shop' => $shop]));
    }

    public function testCatalogPatchRejectsBlankPriceWithoutChangingCurrentPrice(): void
    {
        $merchant = $this->createUser('merchant-catalog-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Délice', 'Yaourts', 'Yaourt prix vide');
        $merchantProduct = $this->createMerchantProduct($shop, $productReference);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()),
            ['price_tnd' => ''],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        $persistedMerchantProduct = $this->entityManager->getRepository(MerchantProduct::class)->find($merchantProduct->getId());
        self::assertInstanceOf(MerchantProduct::class, $persistedMerchantProduct);
        self::assertSame('1.500', $persistedMerchantProduct->getPriceTnd());
    }

    public function testCatalogPatchRejectsPriceTooLargeForDatabasePrecision(): void
    {
        $merchant = $this->createUser('merchant-catalog-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Délice', 'Yaourts', 'Yaourt prix trop grand');
        $merchantProduct = $this->createMerchantProduct($shop, $productReference);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()),
            ['price_tnd' => '99999999.999'],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        $persistedMerchantProduct = $this->entityManager->getRepository(MerchantProduct::class)->find($merchantProduct->getId());
        self::assertInstanceOf(MerchantProduct::class, $persistedMerchantProduct);
        self::assertSame('1.500', $persistedMerchantProduct->getPriceTnd());
    }

    public function testCatalogCreateRejectsUnapprovedProductReference(): void
    {
        $merchant = $this->createUser('merchant-catalog-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference(
            'Randa',
            'Pâtes',
            'Spaghetti non approuvé',
            ProductReferenceStatus::Draft,
        );

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()),
            $this->validCatalogCreatePayload($productReference),
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(MerchantProduct::class)->count(['shop' => $shop]));
    }

    public function testCatalogMutationsDoNotModifyProductReference(): void
    {
        $merchant = $this->createUser('merchant-catalog-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Jouda', 'Conserves', 'Harissa');

        $originalName = $productReference->getNameFr();
        $originalBrand = $productReference->getBrand()->getCanonicalName();
        $originalCategory = $productReference->getCategory()->getNameFr();

        $postResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()),
            $this->validCatalogCreatePayload($productReference, ['price_tnd' => '1.200']),
            $merchant,
        );
        $createdMerchantProduct = $this->entityManager->getRepository(MerchantProduct::class)->findOneForShopAndProductReference($shop, $productReference);
        self::assertInstanceOf(MerchantProduct::class, $createdMerchantProduct);

        $patchResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $createdMerchantProduct->getId()),
            [
                'price_tnd' => '1.300',
                'is_available' => false,
                'is_visible' => false,
                'merchant_note' => 'Prix marchand ajusté',
            ],
            $merchant,
        );

        self::assertSame(201, $postResponse->getStatusCode());
        self::assertSame(200, $patchResponse->getStatusCode());

        $persistedProductReference = $this->entityManager->getRepository(ProductReference::class)->find($productReference->getId());
        self::assertInstanceOf(ProductReference::class, $persistedProductReference);
        self::assertSame($originalName, $persistedProductReference->getNameFr());
        self::assertSame($originalBrand, $persistedProductReference->getBrand()->getCanonicalName());
        self::assertSame($originalCategory, $persistedProductReference->getCategory()->getNameFr());
        self::assertFalse(method_exists(ProductReference::class, 'getPriceTnd'));
        self::assertFalse(method_exists(ProductReference::class, 'isAvailable'));
        self::assertFalse(method_exists(ProductReference::class, 'isVisible'));
    }

    private function createProductReference(
        string $brandName,
        string $categoryName,
        string $nameFr,
        ProductReferenceStatus $status = ProductReferenceStatus::Approved,
    ): ProductReference {
        $suffix = (string) $this->entityManager->getRepository(ProductReference::class)->count([]);
        $brand = (new Brand())
            ->setCanonicalName($brandName)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $brandName)).'-'.$suffix);
        $category = (new Category())
            ->setNameFr($categoryName)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $categoryName)).'-'.$suffix);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setVolume('1.000')
            ->setUnit(ProductUnit::Litre)
            ->setStatus($status);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();

        return $productReference;
    }

    private function createMerchantProduct(Shop $shop, ProductReference $productReference): MerchantProduct
    {
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('1.500');

        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validCatalogCreatePayload(ProductReference $productReference, array $overrides = []): array
    {
        return array_replace([
            'product_reference_id' => $productReference->getId()->toRfc4122(),
            'price_tnd' => '1.650',
            'is_available' => true,
            'is_visible' => true,
            'merchant_note' => null,
        ], $overrides);
    }
}
