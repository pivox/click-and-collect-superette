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
use Symfony\Component\Uid\Uuid;

final class PublicStoreCatalogApiTest extends FunctionalApiTestCase
{
    public function testPublicStoreCatalogCanBeReadWithoutJwt(): void
    {
        $shop = $this->createShop();
        $productReference = $this->createProductReference(
            brandName: 'Vitalait',
            categoryName: 'Lait & produits laitiers',
            categorySlug: 'lait-produits-laitiers',
            nameFr: 'Lait demi-écrémé',
        );
        $merchantProduct = $this->createMerchantProduct($shop, $productReference, merchantNote: 'Note interne marchand');

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertArrayHasKey('items', $payload);
        self::assertCount(1, $payload['items']);
        self::assertSame($merchantProduct->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame($productReference->getId()->toRfc4122(), $payload['items'][0]['product_reference_id']);
        self::assertSame('Lait demi-écrémé', $payload['items'][0]['name_fr']);
        self::assertSame('Vitalait', $payload['items'][0]['brand']);
        self::assertSame('Lait & produits laitiers', $payload['items'][0]['category']);
        self::assertSame('1.000', $payload['items'][0]['volume']);
        self::assertSame('litre', $payload['items'][0]['unit']);
        self::assertSame('1.650', $payload['items'][0]['price_tnd']);
        self::assertTrue($payload['items'][0]['is_available']);
        self::assertArrayNotHasKey('merchant_note', $payload['items'][0]);
        self::assertArrayNotHasKey('is_visible', $payload['items'][0]);
    }

    public function testPublicStoreCatalogReturnsNotFoundForUnknownOrInactiveShop(): void
    {
        $inactiveShop = $this->createShop(active: false);

        $inactiveResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $inactiveShop->getId()));
        $unknownResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog', Uuid::v4()));

        self::assertSame(404, $inactiveResponse->getStatusCode());
        self::assertSame(404, $unknownResponse->getStatusCode());
    }

    public function testPublicStoreCatalogShowsOnlyVisibleAndAvailableProductsForRequestedShop(): void
    {
        $shop = $this->createShop();
        $otherShop = $this->createShop();
        $visibleReference = $this->createProductReference('Vitalait', 'Lait & produits laitiers', 'lait-produits-laitiers', 'Lait visible');
        $hiddenReference = $this->createProductReference('Délice', 'Yaourts', 'yaourts', 'Yaourt invisible');
        $unavailableReference = $this->createProductReference('Safia', 'Eaux', 'eaux', 'Eau indisponible');
        $otherShopReference = $this->createProductReference('Randa', 'Pâtes', 'pates', 'Spaghetti autre supérette');

        $visibleProduct = $this->createMerchantProduct($shop, $visibleReference);
        $this->createMerchantProduct($shop, $hiddenReference, isVisible: false);
        $this->createMerchantProduct($shop, $unavailableReference, isAvailable: false);
        $this->createMerchantProduct($otherShop, $otherShopReference);

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertCount(1, $payload['items']);
        self::assertSame($visibleProduct->getId()->toRfc4122(), $payload['items'][0]['id']);
    }

    public function testPublicStoreCatalogCanFilterByQueryAndCategory(): void
    {
        $shop = $this->createShop();
        $milkReference = $this->createProductReference('Vitalait', 'Lait & produits laitiers', 'lait-produits-laitiers', 'Lait demi-écrémé');
        $yogurtReference = $this->createProductReference('Délice', 'Yaourts', 'yaourts', 'Yaourt nature');
        $waterReference = $this->createProductReference('Safia', 'Eaux', 'eaux', 'Eau minérale');

        $this->createMerchantProduct($shop, $milkReference);
        $this->createMerchantProduct($shop, $yogurtReference);
        $this->createMerchantProduct($shop, $waterReference);

        $queryResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog?query=lait', $shop->getId()));
        $brandResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog?query=safia', $shop->getId()));
        $categoryResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog?category=lait-produits-laitiers', $shop->getId()));

        self::assertSame(200, $queryResponse->getStatusCode());
        self::assertSame(200, $brandResponse->getStatusCode());
        self::assertSame(200, $categoryResponse->getStatusCode());

        $queryPayload = $this->decodeJson($queryResponse);
        $brandPayload = $this->decodeJson($brandResponse);
        $categoryPayload = $this->decodeJson($categoryResponse);

        self::assertCount(1, $queryPayload['items']);
        self::assertSame('Lait demi-écrémé', $queryPayload['items'][0]['name_fr']);
        self::assertCount(1, $brandPayload['items']);
        self::assertSame('Safia', $brandPayload['items'][0]['brand']);
        self::assertCount(1, $categoryPayload['items']);
        self::assertSame('Lait & produits laitiers', $categoryPayload['items'][0]['category']);
    }

    private function createProductReference(
        string $brandName,
        string $categoryName,
        string $categorySlug,
        string $nameFr,
    ): ProductReference {
        $suffix = (string) $this->entityManager->getRepository(ProductReference::class)->count([]);
        $brand = (new Brand())
            ->setCanonicalName($brandName)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $brandName)).'-'.$suffix);
        $category = (new Category())
            ->setNameFr($categoryName)
            ->setSlug($categorySlug);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setVolume('1.000')
            ->setUnit(ProductUnit::Litre)
            ->setStatus(ProductReferenceStatus::Approved);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();

        return $productReference;
    }

    private function createMerchantProduct(
        Shop $shop,
        ProductReference $productReference,
        bool $isVisible = true,
        bool $isAvailable = true,
        ?string $merchantNote = null,
    ): MerchantProduct {
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('1.650')
            ->setVisible($isVisible)
            ->setAvailable($isAvailable)
            ->setMerchantNote($merchantNote);

        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }
}
