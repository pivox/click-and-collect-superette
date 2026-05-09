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

final class ProductReferenceSearchApiTest extends FunctionalApiTestCase
{
    public function testMerchantCanSearchProductReferencesByName(): void
    {
        $merchant = $this->createUser('merchant-search@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $this->createProductReference('Vitalait', 'Laits', 'Lait demi-écrémé');
        $this->createProductReference('Délice', 'Yaourts', 'Yaourt nature');

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/product-references?q=Vitalait', $shop->getId()),
            user: $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayHasKey('items', $payload);
        self::assertArrayHasKey('total', $payload);
        self::assertArrayHasKey('page', $payload);
        self::assertArrayHasKey('limit', $payload);
        self::assertCount(1, $payload['items']);
        self::assertSame('Lait demi-écrémé', $payload['items'][0]['name_fr']);
        self::assertSame('Vitalait', $payload['items'][0]['brand']);
        self::assertSame(1, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(20, $payload['limit']);
    }

    public function testSearchByBarcodeReturnsExactMatch(): void
    {
        $merchant = $this->createUser('merchant-barcode@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $target = $this->createProductReference('Safia', 'Eaux', 'Eau 1.5L', barcode: '6191234567890');
        $this->createProductReference('Vitalait', 'Laits', 'Lait entier');

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/product-references?q=6191234567890', $shop->getId()),
            user: $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertSame($target->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('6191234567890', $payload['items'][0]['barcode']);
    }

    public function testAlreadyInCatalogFlagIsTrueForExistingCatalogProduct(): void
    {
        $merchant = $this->createUser('merchant-flag@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Randa', 'Pâtes', 'Spaghetti');
        $this->createMerchantProduct($shop, $productReference);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/product-references?q=Spaghetti', $shop->getId()),
            user: $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertTrue($payload['items'][0]['already_in_catalog']);
    }

    public function testAlreadyInCatalogFlagIsFalseForProductNotInCatalog(): void
    {
        $merchant = $this->createUser('merchant-flag-false@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $this->createProductReference('Jouda', 'Conserves', 'Harissa');

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/product-references?q=Harissa', $shop->getId()),
            user: $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertFalse($payload['items'][0]['already_in_catalog']);
    }

    public function testNoResultsForUnknownSearchTerm(): void
    {
        $merchant = $this->createUser('merchant-noresult@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $this->createProductReference('Vitalait', 'Laits', 'Lait entier');

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/product-references?q=produit-inconnu-xyz', $shop->getId()),
            user: $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(0, $payload['items']);
        self::assertSame(0, $payload['total']);
    }

    public function testUserWithoutMerchantRoleIsRefused(): void
    {
        $merchant = $this->createUser('merchant-owner-403@example.test', ['ROLE_MERCHANT']);
        $client = $this->createUser('client-search@example.test');
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/product-references', $shop->getId()),
            user: $client,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testNonOwnerMerchantIsRefused(): void
    {
        $owner = $this->createUser('merchant-owner-nonowner@example.test', ['ROLE_MERCHANT']);
        $other = $this->createUser('merchant-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/product-references', $shop->getId()),
            user: $other,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testPaginationParametersAreRespected(): void
    {
        $merchant = $this->createUser('merchant-pagination@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        for ($i = 1; $i <= 5; ++$i) {
            $this->createProductReference('BrandPage', 'Catégorie', 'Produit '.$i);
        }

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/product-references?q=Produit&page=1&limit=2', $shop->getId()),
            user: $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(2, $payload['items']);
        self::assertSame(5, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(2, $payload['limit']);
    }

    private function createProductReference(
        string $brandName,
        string $categoryName,
        string $nameFr,
        ProductReferenceStatus $status = ProductReferenceStatus::Approved,
        ?string $barcode = null,
    ): ProductReference {
        $suffix = (string) $this->entityManager->getRepository(ProductReference::class)->count([]);
        $brand = (new Brand())
            ->setCanonicalName($brandName)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $brandName) ?? '').'-'.$suffix);
        $category = (new Category())
            ->setNameFr($categoryName)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $categoryName) ?? '').'-'.$suffix);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setVolume('1.000')
            ->setUnit(ProductUnit::Litre)
            ->setBarcode($barcode)
            ->setStatus($status);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();

        return $productReference;
    }

    private function createMerchantProduct(
        Shop $shop,
        ProductReference $productReference,
    ): MerchantProduct {
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('1.500');

        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }
}
