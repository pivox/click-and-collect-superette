<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantLocalProduct;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;

final class MerchantLocalProductApiTest extends FunctionalApiTestCase
{
    public function testOwnerMerchantCanCreateLocalProductAndSeeItInCatalog(): void
    {
        $merchant = $this->createUser('merchant-local-product-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $referenceProduct = $this->createProductReference('Vitalait', 'Lait & produits laitiers', 'Lait demi-écrémé');
        $this->createMerchantProduct($shop, $referenceProduct);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/local-products', $shop->getId()),
            $this->validLocalProductPayload(),
            $merchant,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayHasKey('merchant_product_id', $payload);
        self::assertArrayHasKey('local_product_id', $payload);
        self::assertSame('Harissa maison', $payload['name_fr']);
        self::assertNull($payload['name_ar']);
        self::assertNull($payload['brand']);
        self::assertSame('Epicerie', $payload['category']);
        self::assertSame('350.000', $payload['volume']);
        self::assertSame('gramme', $payload['unit']);
        self::assertSame('4.500', $payload['price_tnd']);
        self::assertTrue($payload['is_available']);
        self::assertTrue($payload['is_visible']);
        self::assertNull($payload['merchant_note']);

        $localProduct = $this->entityManager->getRepository(MerchantLocalProduct::class)->find($payload['local_product_id']);
        self::assertInstanceOf(MerchantLocalProduct::class, $localProduct);
        self::assertTrue($localProduct->getShop()->getId()->equals($shop->getId()));

        $merchantProduct = $this->entityManager->getRepository(MerchantProduct::class)->find($payload['merchant_product_id']);
        self::assertInstanceOf(MerchantProduct::class, $merchantProduct);
        self::assertNull($merchantProduct->getProductReference());
        self::assertSame($localProduct, $merchantProduct->getLocalProduct());

        $catalogResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $merchant);

        self::assertSame(200, $catalogResponse->getStatusCode());
        $catalog = $this->decodeJson($catalogResponse);
        self::assertCount(2, $catalog);
        $localCatalogItem = $this->findCatalogItem($catalog, $payload['merchant_product_id']);
        self::assertNull($localCatalogItem['product_reference_id']);
        self::assertSame($payload['local_product_id'], $localCatalogItem['local_product_id']);
        self::assertSame('Harissa maison', $localCatalogItem['name_fr']);
        self::assertNull($localCatalogItem['brand']);
        self::assertSame('Epicerie', $localCatalogItem['category']);
        self::assertSame('350.000', $localCatalogItem['volume']);
        self::assertSame('gramme', $localCatalogItem['unit']);
        self::assertSame('4.500', $localCatalogItem['price_tnd']);
        self::assertTrue($localCatalogItem['is_available']);
        self::assertTrue($localCatalogItem['is_visible']);
        self::assertNull($localCatalogItem['merchant_note']);

        $referenceCatalogItem = $this->findCatalogItem($catalog, $this->entityManager->getRepository(MerchantProduct::class)->findOneForShopAndProductReference($shop, $referenceProduct)?->getId()->toRfc4122());
        self::assertSame($referenceProduct->getId()->toRfc4122(), $referenceCatalogItem['product_reference_id']);
        self::assertNull($referenceCatalogItem['local_product_id']);

        $publicCatalogResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shop->getId()));
        self::assertSame(200, $publicCatalogResponse->getStatusCode());
        $publicCatalog = $this->decodeJson($publicCatalogResponse);
        self::assertArrayHasKey('items', $publicCatalog);
        $publicLocalItem = $this->findCatalogItem($publicCatalog['items'], $payload['merchant_product_id']);
        self::assertNull($publicLocalItem['product_reference_id']);
        self::assertSame($payload['local_product_id'], $publicLocalItem['local_product_id']);
        self::assertSame('Harissa maison', $publicLocalItem['name_fr']);
        self::assertSame('4.500', $publicLocalItem['price_tnd']);
    }

    public function testNonOwnerMerchantCannotCreateLocalProductForAnotherShop(): void
    {
        $owner = $this->createUser('merchant-local-product-real-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-local-product-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/local-products', $shop->getId()),
            $this->validLocalProductPayload(),
            $otherMerchant,
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(MerchantLocalProduct::class)->count([]));
        self::assertSame(0, $this->entityManager->getRepository(MerchantProduct::class)->count([]));
    }

    public function testClientAnonymousAndAdminWithoutMerchantRoleAreDenied(): void
    {
        $merchant = $this->createUser('merchant-local-product-security-owner@example.test', ['ROLE_MERCHANT']);
        $client = $this->createUser('client-local-product@example.test');
        $admin = $this->createUser('admin-local-product@example.test', ['ROLE_ADMIN']);
        $shop = $this->createShop($merchant);

        $clientResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/local-products', $shop->getId()),
            $this->validLocalProductPayload(['name_fr' => 'Produit client']),
            $client,
        );
        $anonymousResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/local-products', $shop->getId()),
            $this->validLocalProductPayload(['name_fr' => 'Produit anonyme']),
        );
        $adminResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/local-products', $shop->getId()),
            $this->validLocalProductPayload(['name_fr' => 'Produit admin']),
            $admin,
        );

        self::assertSame(403, $clientResponse->getStatusCode());
        self::assertContains($anonymousResponse->getStatusCode(), [401, 403]);
        self::assertSame(403, $adminResponse->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(MerchantLocalProduct::class)->count([]));
        self::assertSame(0, $this->entityManager->getRepository(MerchantProduct::class)->count([]));
    }

    public function testCreatingLocalProductDoesNotCreateProductReference(): void
    {
        $merchant = $this->createUser('merchant-local-product-no-reference@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $existingReference = $this->createProductReference('Jouda', 'Conserves', 'Harissa référentiel');
        $initialProductReferenceCount = $this->entityManager->getRepository(ProductReference::class)->count([]);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/local-products', $shop->getId()),
            $this->validLocalProductPayload(['name_fr' => 'Harissa maison locale']),
            $merchant,
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertSame($initialProductReferenceCount, $this->entityManager->getRepository(ProductReference::class)->count([]));
        $persistedReference = $this->entityManager->getRepository(ProductReference::class)->find($existingReference->getId());
        self::assertInstanceOf(ProductReference::class, $persistedReference);
        self::assertSame(ProductReferenceStatus::Approved, $persistedReference->getStatus());
    }

    public function testCreatingLocalProductRejectsBlankFrenchNameAfterTrim(): void
    {
        $merchant = $this->createUser('merchant-local-product-blank-name@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/local-products', $shop->getId()),
            $this->validLocalProductPayload(['name_fr' => '   ']),
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(MerchantLocalProduct::class)->count([]));
        self::assertSame(0, $this->entityManager->getRepository(MerchantProduct::class)->count([]));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validLocalProductPayload(array $overrides = []): array
    {
        return array_replace([
            'name_fr' => 'Harissa maison',
            'name_ar' => null,
            'brand_name' => null,
            'volume' => '350',
            'unit' => 'gramme',
            'barcode' => null,
            'default_category_name' => 'Epicerie',
            'price_tnd' => '4.500',
            'is_available' => true,
            'is_visible' => true,
            'merchant_note' => null,
        ], $overrides);
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
     * @param list<array<string, mixed>> $catalog
     *
     * @return array<string, mixed>
     */
    private function findCatalogItem(array $catalog, ?string $merchantProductId): array
    {
        self::assertNotNull($merchantProductId);

        foreach ($catalog as $item) {
            if (($item['id'] ?? null) === $merchantProductId) {
                return $item;
            }
        }

        self::fail(\sprintf('Catalog item %s not found.', $merchantProductId));
    }
}
