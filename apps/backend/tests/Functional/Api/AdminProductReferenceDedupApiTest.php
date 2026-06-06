<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\ProductReferenceMergeHistory;
use App\Entity\Shop;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;

final class AdminProductReferenceDedupApiTest extends FunctionalApiTestCase
{
    public function testAdminListsDuplicateCandidatesWithBarcodePriority(): void
    {
        $admin = $this->createUser('admin-pr-dedup-list@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Vitalait', 'vitalait-dedup-list');
        $category = $this->createCategory('Laits', 'laits-dedup-list');
        $this->createProductReference($brand, $category, 'Lait demi écrémé', barcode: '6191000000011');
        $this->createProductReference($brand, $category, 'Produit autre nom', barcode: '6191000000011');
        $this->createProductReference($brand, $category, 'Yaourt nature', volume: '110', unit: ProductUnit::Gramme);
        $this->createProductReference($brand, $category, 'Yaourt nature', volume: '110', unit: ProductUnit::Gramme);

        $response = $this->requestJson('GET', '/api/admin/product-references/duplicates', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertGreaterThanOrEqual(2, $payload['total']);
        self::assertSame('barcode', $payload['items'][0]['reason']);
        self::assertSame('6191000000011', $payload['items'][0]['barcode']);
    }

    public function testAdminComparesTwoProductReferencesBeforeMerge(): void
    {
        $admin = $this->createUser('admin-pr-dedup-compare@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Délice', 'delice-dedup-compare');
        $category = $this->createCategory('Yaourts', 'yaourts-dedup-compare');
        $left = $this->createProductReference($brand, $category, 'Yaourt nature', volume: '110', unit: ProductUnit::Gramme);
        $right = $this->createProductReference($brand, $category, 'Yaourt nature', volume: '110', unit: ProductUnit::Gramme);
        $this->createMerchantProduct($this->createShop(), $right);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/admin/product-references/compare?left=%s&right=%s', $left->getId(), $right->getId()),
            user: $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($left->getId()->toRfc4122(), $payload['left']['id']);
        self::assertSame($right->getId()->toRfc4122(), $payload['right']['id']);
        self::assertSame(0, $payload['left_offer_count']);
        self::assertSame(1, $payload['right_offer_count']);
        self::assertSame('identity', $payload['reason']);
    }

    public function testAdminMergesProductReferenceAndMovesMerchantOffers(): void
    {
        $admin = $this->createUser('admin-pr-dedup-merge@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Randa', 'randa-dedup-merge');
        $category = $this->createCategory('Pâtes', 'pates-dedup-merge');
        $kept = $this->createProductReference($brand, $category, 'Spaghetti', barcode: '6192000000011');
        $absorbed = $this->createProductReference($brand, $category, 'Spaghetti', barcode: '6192000000011');
        $merchantProduct = $this->createMerchantProduct($this->createShop(), $absorbed);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s/merge', $absorbed->getId()),
            ['keptProductReferenceId' => $kept->getId()->toRfc4122()],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($kept->getId()->toRfc4122(), $payload['kept']['id']);
        self::assertSame($absorbed->getId()->toRfc4122(), $payload['absorbed']['id']);
        self::assertSame(1, $payload['moved_offer_count']);

        $this->entityManager->clear();
        $updatedOffer = $this->entityManager->find(MerchantProduct::class, $merchantProduct->getId());
        $updatedAbsorbed = $this->entityManager->find(ProductReference::class, $absorbed->getId());
        self::assertInstanceOf(MerchantProduct::class, $updatedOffer);
        self::assertInstanceOf(ProductReference::class, $updatedAbsorbed);
        self::assertSame($kept->getId()->toRfc4122(), $updatedOffer->getProductReference()?->getId()->toRfc4122());
        self::assertSame(ProductReferenceStatus::Archived, $updatedAbsorbed->getStatus());
        self::assertSame(1, $this->entityManager->getRepository(ProductReferenceMergeHistory::class)->count([]));
        self::assertSame(1, $this->entityManager->getRepository(AdminAuditLog::class)->count(['action' => 'product_reference.merge']));
    }

    public function testMergeArchivedReferenceReturns422(): void
    {
        $admin = $this->createUser('admin-pr-dedup-archived@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Safia', 'safia-dedup-archived');
        $category = $this->createCategory('Eaux', 'eaux-dedup-archived');
        $kept = $this->createProductReference($brand, $category, 'Eau minérale', status: ProductReferenceStatus::Archived);
        $absorbed = $this->createProductReference($brand, $category, 'Eau minérale');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s/merge', $absorbed->getId()),
            ['keptProductReferenceId' => $kept->getId()->toRfc4122()],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testMerchantCannotAccessDedupRoutes(): void
    {
        $merchant = $this->createUser('merchant-pr-dedup@example.test', ['ROLE_MERCHANT']);
        $brand = $this->createBrand('Boga', 'boga-dedup-security');
        $category = $this->createCategory('Boissons', 'boissons-dedup-security');
        $left = $this->createProductReference($brand, $category, 'Boisson gazeuse');
        $right = $this->createProductReference($brand, $category, 'Boisson gazeuse');

        self::assertSame(403, $this->requestJson('GET', '/api/admin/product-references/duplicates', user: $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson(
            'GET',
            \sprintf('/api/admin/product-references/compare?left=%s&right=%s', $left->getId(), $right->getId()),
            user: $merchant,
        )->getStatusCode());
        self::assertSame(403, $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s/merge', $right->getId()),
            ['keptProductReferenceId' => $left->getId()->toRfc4122()],
            $merchant,
        )->getStatusCode());
    }

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

    private function createCategory(string $nameFr, string $slug): Category
    {
        $category = (new Category())
            ->setNameFr($nameFr)
            ->setSlug($slug)
            ->setActive(true);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function createProductReference(
        Brand $brand,
        Category $category,
        string $nameFr,
        ?string $barcode = null,
        ?string $volume = null,
        ProductUnit $unit = ProductUnit::Piece,
        ProductReferenceStatus $status = ProductReferenceStatus::Approved,
    ): ProductReference {
        $ref = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setBarcode($barcode)
            ->setVolume($volume)
            ->setUnit($unit)
            ->setStatus($status);

        $this->entityManager->persist($ref);
        $this->entityManager->flush();

        return $ref;
    }

    private function createMerchantProduct(Shop $shop, ProductReference $productReference): MerchantProduct
    {
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('1.500')
            ->setAvailable(true)
            ->setVisible(true);

        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }
}
