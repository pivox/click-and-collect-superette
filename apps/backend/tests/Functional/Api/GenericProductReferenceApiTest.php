<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceKind;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;

/**
 * PRODUCT-IMAGE-001 (#581): generic shared product references — no brand,
 * no GTIN, shared across merchants with per-merchant price/availability.
 */
final class GenericProductReferenceApiTest extends FunctionalApiTestCase
{
    public function testAdminCreatesAGenericReferenceWithoutBrandNorBarcode(): void
    {
        $admin = $this->createUser('admin-generic@example.test', ['ROLE_ADMIN']);
        $category = $this->createCategory('Fruits et légumes');

        $response = $this->requestJson('POST', '/api/admin/product-references', [
            'nameFr' => 'Tomate',
            'nameAr' => 'طماطم',
            'categoryId' => $category->getId()->toRfc4122(),
            'unit' => 'kilogramme',
            'kind' => 'generic',
            'status' => 'approved',
        ], $admin);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame('Tomate', $payload['name_fr']);
        self::assertSame('generic', $payload['kind']);
        self::assertArrayNotHasKey('brand_id', $payload);
        self::assertArrayNotHasKey('brand_name', $payload);
        self::assertArrayNotHasKey('barcode', $payload);
    }

    public function testGenericReferenceSupportsBotteUnit(): void
    {
        $admin = $this->createUser('admin-generic-botte@example.test', ['ROLE_ADMIN']);
        $category = $this->createCategory('Herbes fraîches');

        $response = $this->requestJson('POST', '/api/admin/product-references', [
            'nameFr' => 'Persil',
            'categoryId' => $category->getId()->toRfc4122(),
            'unit' => 'botte',
            'kind' => 'generic',
        ], $admin);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('botte', $this->decodeJson($response)['unit']);
    }

    public function testGenericReferenceRejectsABrand(): void
    {
        $admin = $this->createUser('admin-generic-brand@example.test', ['ROLE_ADMIN']);
        $category = $this->createCategory('Fruits');
        $brand = $this->createBrand('Marque interdite');

        $response = $this->requestJson('POST', '/api/admin/product-references', [
            'nameFr' => 'Banane',
            'categoryId' => $category->getId()->toRfc4122(),
            'unit' => 'kilogramme',
            'kind' => 'generic',
            'brandId' => $brand->getId()->toRfc4122(),
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('ADMIN_PRODUCT_REFERENCE_GENERIC_BRAND_FORBIDDEN', $this->decodeJson($response)['detail']);
    }

    public function testIndustrialReferenceStillRequiresABrand(): void
    {
        $admin = $this->createUser('admin-industrial-nobrand@example.test', ['ROLE_ADMIN']);
        $category = $this->createCategory('Épicerie');

        $response = $this->requestJson('POST', '/api/admin/product-references', [
            'nameFr' => 'Pâtes Spaghetti 500 g',
            'categoryId' => $category->getId()->toRfc4122(),
            'unit' => 'paquet',
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('ADMIN_PRODUCT_REFERENCE_BRAND_REQUIRED', $this->decodeJson($response)['detail']);
    }

    public function testIndustrialReferenceCannotDropItsBrandOnUpdate(): void
    {
        $admin = $this->createUser('admin-industrial-drop@example.test', ['ROLE_ADMIN']);
        $reference = $this->createGenericOrIndustrialReference(generic: false);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s', $reference->getId()),
            ['brandId' => null],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('ADMIN_PRODUCT_REFERENCE_BRAND_REQUIRED', $this->decodeJson($response)['detail']);
    }

    public function testTwoMerchantsShareTheSameGenericReferenceWithOwnPrices(): void
    {
        $reference = $this->createGenericOrIndustrialReference(generic: true);

        $merchantA = $this->createUser('generic-merchant-a@example.test', ['ROLE_MERCHANT']);
        $shopA = $this->createShop($merchantA);
        $merchantB = $this->createUser('generic-merchant-b@example.test', ['ROLE_MERCHANT']);
        $shopB = $this->createShop($merchantB);

        $createA = $this->requestJson('POST', \sprintf('/api/merchant/stores/%s/catalog', $shopA->getId()), [
            'product_reference_id' => $reference->getId()->toRfc4122(),
            'price_tnd' => '2.500',
            'is_available' => true,
            'is_visible' => true,
            'merchant_note' => null,
        ], $merchantA);
        self::assertSame(201, $createA->getStatusCode(), (string) $createA->getContent());

        $createB = $this->requestJson('POST', \sprintf('/api/merchant/stores/%s/catalog', $shopB->getId()), [
            'product_reference_id' => $reference->getId()->toRfc4122(),
            'price_tnd' => '3.100',
            'is_available' => true,
            'is_visible' => true,
            'merchant_note' => null,
        ], $merchantB);
        self::assertSame(201, $createB->getStatusCode(), (string) $createB->getContent());

        // Each public catalog shows the shared generic product at its own price.
        $rawA = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shopA->getId()));
        self::assertSame(200, $rawA->getStatusCode(), (string) $rawA->getContent());
        $catalogA = $this->decodeJson($rawA);
        $catalogB = $this->decodeJson($this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shopB->getId())));
        self::assertCount(1, $catalogA['items']);
        self::assertCount(1, $catalogB['items']);
        self::assertSame('2.500', $catalogA['items'][0]['price_tnd']);
        self::assertSame('3.100', $catalogB['items'][0]['price_tnd']);
    }

    public function testMerchantReferenceSearchReturnsGenericWithoutBrand(): void
    {
        $reference = $this->createGenericOrIndustrialReference(generic: true);
        $merchant = $this->createUser('generic-search@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/product-references?q=Tomate', $shop->getId()),
            user: $merchant,
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertSame($reference->getId()->toRfc4122(), $payload['items'][0]['id']);
        // Nullable brand fields are excluded from JSON when null.
        self::assertArrayNotHasKey('brand_id', $payload['items'][0]);
        self::assertArrayNotHasKey('brand', $payload['items'][0]);
    }

    public function testIndustrialReferencesDoNotRegress(): void
    {
        $admin = $this->createUser('admin-industrial-ok@example.test', ['ROLE_ADMIN']);
        $category = $this->createCategory('Boissons');
        $brand = $this->createBrand('Délice');

        $response = $this->requestJson('POST', '/api/admin/product-references', [
            'nameFr' => 'Lait UHT demi-écrémé 1 L',
            'brandId' => $brand->getId()->toRfc4122(),
            'categoryId' => $category->getId()->toRfc4122(),
            'unit' => 'litre',
            'volume' => '1',
        ], $admin);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame('industrial', $payload['kind']);
        self::assertSame('Délice', $payload['brand_name']);
    }

    // Fixtures

    private function createCategory(string $nameFr): Category
    {
        $suffix = (string) $this->entityManager->getRepository(Category::class)->count([]);
        $category = (new Category())
            ->setNameFr($nameFr)
            ->setSlug(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $nameFr)).'-'.$suffix);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function createBrand(string $canonicalName): Brand
    {
        $suffix = (string) $this->entityManager->getRepository(Brand::class)->count([]);
        $brand = (new Brand())
            ->setCanonicalName($canonicalName)
            ->setSlug(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $canonicalName)).'-'.$suffix);
        $this->entityManager->persist($brand);
        $this->entityManager->flush();

        return $brand;
    }

    private function createGenericOrIndustrialReference(bool $generic): ProductReference
    {
        $category = $this->createCategory('Fruits et légumes');
        $reference = (new ProductReference())
            ->setNameFr('Tomate')
            ->setCategory($category)
            ->setUnit(ProductUnit::Kilogramme)
            ->setStatus(ProductReferenceStatus::Approved);
        if ($generic) {
            $reference->setKind(ProductReferenceKind::Generic);
        } else {
            $reference->setBrand($this->createBrand('Marque Test'));
        }
        $this->entityManager->persist($reference);
        $this->entityManager->flush();

        return $reference;
    }
}
