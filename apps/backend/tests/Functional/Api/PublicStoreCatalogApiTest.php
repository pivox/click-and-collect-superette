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
            nameAr: 'حليب نصف دسم',
            categoryAr: 'الحليب ومشتقاته',
        );
        $merchantProduct = $this->createMerchantProduct($shop, $productReference, merchantNote: 'Note interne marchand');

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertArrayHasKey('items', $payload);
        self::assertCount(1, $payload['items']);
        self::assertSame($merchantProduct->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame($productReference->getId()->toRfc4122(), $payload['items'][0]['product_reference_id']);
        self::assertNull($payload['items'][0]['local_product_id']);
        self::assertSame('Lait demi-écrémé', $payload['items'][0]['name_fr']);
        self::assertSame('حليب نصف دسم', $payload['items'][0]['name_ar']);
        self::assertSame('Vitalait', $payload['items'][0]['brand']);
        self::assertSame('Lait & produits laitiers', $payload['items'][0]['category']);
        self::assertSame('الحليب ومشتقاته', $payload['items'][0]['category_ar']);
        self::assertSame('lait-produits-laitiers', $payload['items'][0]['category_slug']);
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

    public function testPublicStoreCatalogShowsOnlyVisibleAvailableAndApprovedProductsForRequestedShop(): void
    {
        $shop = $this->createShop();
        $otherShop = $this->createShop();
        $visibleReference = $this->createProductReference('Vitalait', 'Lait & produits laitiers', 'lait-produits-laitiers', 'Lait visible');
        $hiddenReference = $this->createProductReference('Délice', 'Yaourts', 'yaourts', 'Yaourt invisible');
        $unavailableReference = $this->createProductReference('Safia', 'Eaux', 'eaux', 'Eau indisponible');
        $otherShopReference = $this->createProductReference('Randa', 'Pâtes', 'pates', 'Spaghetti autre supérette');
        $draftReference = $this->createProductReference('Nadhif', 'Entretien', 'entretien', 'Produit brouillon', status: ProductReferenceStatus::Draft);
        $pendingReviewReference = $this->createProductReference('Nadhif Plus', 'Hygiène', 'hygiene', 'Produit en revue', status: ProductReferenceStatus::PendingReview);
        $archivedReference = $this->createProductReference('Jouda', 'Conserves', 'conserves', 'Harissa archivée', status: ProductReferenceStatus::Archived);
        $rejectedReference = $this->createProductReference('Candia', 'Boissons', 'boissons', 'Boisson rejetée', status: ProductReferenceStatus::Rejected);

        $visibleProduct = $this->createMerchantProduct($shop, $visibleReference);
        $this->createMerchantProduct($shop, $hiddenReference, isVisible: false);
        $this->createMerchantProduct($shop, $unavailableReference, isAvailable: false);
        $this->createMerchantProduct($otherShop, $otherShopReference);
        $this->createMerchantProduct($shop, $draftReference);
        $this->createMerchantProduct($shop, $pendingReviewReference);
        $this->createMerchantProduct($shop, $archivedReference);
        $this->createMerchantProduct($shop, $rejectedReference);

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertCount(1, $payload['items']);
        self::assertSame($visibleProduct->getId()->toRfc4122(), $payload['items'][0]['id']);
    }

    public function testPublicStoreCatalogCanFilterByQueryAndCategory(): void
    {
        $shop = $this->createShop();
        $milkReference = $this->createProductReference(
            brandName: 'Vitalait',
            categoryName: 'Lait & produits laitiers',
            categorySlug: 'lait-produits-laitiers',
            nameFr: 'Lait demi-écrémé',
            nameAr: 'حليب نصف دسم',
            variantFr: 'Demi-écrémé',
        );
        $yogurtReference = $this->createProductReference('Délice', 'Yaourts', 'yaourts', 'Yaourt nature', volume: '110.000', unit: ProductUnit::Gramme);
        $waterReference = $this->createProductReference('Safia', 'Eaux', 'eaux', 'Eau minérale', volume: '1.500');

        $this->createMerchantProduct($shop, $milkReference);
        $this->createMerchantProduct($shop, $yogurtReference);
        $this->createMerchantProduct($shop, $waterReference);

        $queryResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog?query=lait', $shop->getId()));
        $brandResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog?query=safia', $shop->getId()));
        $unitResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog?query=litre', $shop->getId()));
        $compactFormatResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog?query=1l', $shop->getId()));
        $accentFoldedResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog?query=ecreme', $shop->getId()));
        $arabicResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog?query=%s', $shop->getId(), rawurlencode('حليب')));
        $categoryResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog?category=lait-produits-laitiers', $shop->getId()));

        self::assertSame(200, $queryResponse->getStatusCode());
        self::assertSame(200, $brandResponse->getStatusCode());
        self::assertSame(200, $unitResponse->getStatusCode());
        self::assertSame(200, $compactFormatResponse->getStatusCode());
        self::assertSame(200, $accentFoldedResponse->getStatusCode());
        self::assertSame(200, $arabicResponse->getStatusCode());
        self::assertSame(200, $categoryResponse->getStatusCode());

        $queryPayload = $this->decodeJson($queryResponse);
        $brandPayload = $this->decodeJson($brandResponse);
        $unitPayload = $this->decodeJson($unitResponse);
        $compactFormatPayload = $this->decodeJson($compactFormatResponse);
        $accentFoldedPayload = $this->decodeJson($accentFoldedResponse);
        $arabicPayload = $this->decodeJson($arabicResponse);
        $categoryPayload = $this->decodeJson($categoryResponse);

        self::assertCount(1, $queryPayload['items']);
        self::assertSame('Lait demi-écrémé', $queryPayload['items'][0]['name_fr']);
        self::assertCount(1, $brandPayload['items']);
        self::assertSame('Safia', $brandPayload['items'][0]['brand']);
        self::assertCount(2, $unitPayload['items']);
        self::assertCount(1, $compactFormatPayload['items']);
        self::assertSame('Lait demi-écrémé', $compactFormatPayload['items'][0]['name_fr']);
        self::assertCount(1, $accentFoldedPayload['items']);
        self::assertSame('Lait demi-écrémé', $accentFoldedPayload['items'][0]['name_fr']);
        self::assertCount(1, $arabicPayload['items']);
        self::assertSame('حليب نصف دسم', $arabicPayload['items'][0]['name_ar']);
        self::assertCount(1, $categoryPayload['items']);
        self::assertSame('Lait & produits laitiers', $categoryPayload['items'][0]['category']);
    }

    public function testPublicStoreCatalogIsPaginatedAndKeepsCategoryOptions(): void
    {
        $shop = $this->createShop();

        foreach (['Abricots', 'Bananes', 'Carottes', 'Dattes', 'Eau minérale'] as $index => $name) {
            $reference = $this->createProductReference(
                brandName: 'Marque '.$index,
                categoryName: 'Catégorie '.$index,
                categorySlug: 'categorie-'.$index,
                nameFr: $name,
            );
            $this->createMerchantProduct($shop, $reference);
        }

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog?page=2&items_per_page=2', $shop->getId()));

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertSame(2, $payload['page']);
        self::assertSame(2, $payload['items_per_page']);
        self::assertSame(5, $payload['total']);
        self::assertSame(3, $payload['pages']);
        self::assertCount(2, $payload['items']);
        self::assertSame(['Carottes', 'Dattes'], array_column($payload['items'], 'name_fr'));
        self::assertCount(5, $payload['categories']);
        self::assertSame(
            ['categorie-0', 'categorie-1', 'categorie-2', 'categorie-3', 'categorie-4'],
            array_column($payload['categories'], 'key'),
        );
    }

    private function createProductReference(
        string $brandName,
        string $categoryName,
        string $categorySlug,
        string $nameFr,
        ?string $nameAr = null,
        ?string $categoryAr = null,
        ?string $variantFr = null,
        ?string $variantAr = null,
        string $volume = '1.000',
        ProductUnit $unit = ProductUnit::Litre,
        ProductReferenceStatus $status = ProductReferenceStatus::Approved,
    ): ProductReference {
        $suffix = (string) $this->entityManager->getRepository(ProductReference::class)->count([]);
        $brand = (new Brand())
            ->setCanonicalName($brandName)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $brandName)).'-'.$suffix);
        $category = (new Category())
            ->setNameFr($categoryName)
            ->setNameAr($categoryAr)
            ->setSlug($categorySlug);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setNameAr($nameAr)
            ->setVariantFr($variantFr)
            ->setVariantAr($variantAr)
            ->setVolume($volume)
            ->setUnit($unit)
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
