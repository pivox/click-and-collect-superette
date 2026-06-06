<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Kadhia;
use App\Entity\KadhiaLine;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\ProductReference;
use App\Entity\ProductReferenceMergeHistory;
use App\Entity\Shop;
use App\Entity\User;
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

    public function testAdminCanUpdateKeptReferenceAfterMergingOlderBarcodeDuplicate(): void
    {
        $admin = $this->createUser('admin-pr-dedup-update-kept@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Jadida', 'jadida-dedup-update-kept');
        $category = $this->createCategory('Fromages', 'fromages-dedup-update-kept');
        $absorbed = $this->createProductReference($brand, $category, 'Fromage tranche', barcode: '6192500000011');
        $kept = $this->createProductReference($brand, $category, 'Fromage tranches', barcode: '6192500000011');

        self::assertSame(200, $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s/merge', $absorbed->getId()),
            ['keptProductReferenceId' => $kept->getId()->toRfc4122()],
            $admin,
        )->getStatusCode());

        $this->entityManager->clear();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s', $kept->getId()),
            [
                'nameFr' => 'Fromage tranches corrigé',
                'barcode' => '6192500000011',
            ],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('6192500000011', $payload['barcode']);
        self::assertSame('Fromage tranches corrigé', $payload['name_fr']);
    }

    public function testMergePreservesKadhiaAndOrderLinePriceContributionsWhenShopHasBothOffers(): void
    {
        $admin = $this->createUser('admin-pr-dedup-conflict@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-pr-dedup-conflict@example.test', ['ROLE_CUSTOMER']);
        $brand = $this->createBrand('Céréal', 'cereal-dedup-conflict');
        $category = $this->createCategory('Petit déjeuner', 'petit-dejeuner-dedup-conflict');
        $kept = $this->createProductReference($brand, $category, 'Corn flakes', barcode: '6193000000011');
        $absorbed = $this->createProductReference($brand, $category, 'Corn flakes', barcode: '6193000000011');
        $shop = $this->createShop();
        $keptOffer = $this->createMerchantProduct($shop, $kept, '1.000');
        $absorbedOffer = $this->createMerchantProduct($shop, $absorbed, '2.500');
        $kadhia = $this->createKadhiaWithLines($customer, $shop, $keptOffer, $absorbedOffer);
        $order = $this->createOrderWithLines($customer, $shop, $keptOffer, $absorbedOffer);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-references/%s/merge', $absorbed->getId()),
            ['keptProductReferenceId' => $kept->getId()->toRfc4122()],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(0, $payload['moved_offer_count']);
        self::assertSame(1, $payload['removed_conflicting_offer_count']);

        $this->entityManager->clear();
        $updatedKadhiaLines = $this->entityManager->getRepository(KadhiaLine::class)->findBy(['kadhia' => $kadhia]);
        $updatedOrder = $this->entityManager->find(Order::class, $order->getId());
        $updatedOrderLines = $this->entityManager->getRepository(OrderLine::class)->findBy(['order' => $order]);

        self::assertCount(1, $updatedKadhiaLines);
        self::assertSame(3, $updatedKadhiaLines[0]->getQuantity());
        self::assertSame(0, bccomp('2.000', $updatedKadhiaLines[0]->getUnitPriceTnd(), 3));
        self::assertSame($keptOffer->getId()->toRfc4122(), $updatedKadhiaLines[0]->getMerchantProduct()->getId()->toRfc4122());
        self::assertCount(1, $updatedOrderLines);
        self::assertSame(3, $updatedOrderLines[0]->getQuantity());
        self::assertSame(0, bccomp('2.000', $updatedOrderLines[0]->getUnitPriceTnd(), 3));
        self::assertSame(0, bccomp('6.000', $updatedOrderLines[0]->getLineTotalTnd(), 3));
        self::assertFalse($updatedOrderLines[0]->isPrepared());
        self::assertInstanceOf(Order::class, $updatedOrder);
        self::assertSame(0, bccomp('6.000', $updatedOrder->getTotalTnd(), 3));
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

    private function createMerchantProduct(Shop $shop, ProductReference $productReference, string $priceTnd = '1.500'): MerchantProduct
    {
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd($priceTnd)
            ->setAvailable(true)
            ->setVisible(true);

        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }

    private function createKadhiaWithLines(
        User $customer,
        Shop $shop,
        MerchantProduct $keptOffer,
        MerchantProduct $absorbedOffer,
    ): Kadhia {
        $kadhia = (new Kadhia())
            ->setCustomer($customer)
            ->setShop($shop);
        $keptLine = (new KadhiaLine())
            ->setKadhia($kadhia)
            ->setMerchantProduct($keptOffer)
            ->setQuantity(1)
            ->setUnitPriceTnd('1.000');
        $absorbedLine = (new KadhiaLine())
            ->setKadhia($kadhia)
            ->setMerchantProduct($absorbedOffer)
            ->setQuantity(2)
            ->setUnitPriceTnd('2.500');

        $this->entityManager->persist($kadhia);
        $this->entityManager->persist($keptLine);
        $this->entityManager->persist($absorbedLine);
        $this->entityManager->flush();

        return $kadhia;
    }

    private function createOrderWithLines(
        User $customer,
        Shop $shop,
        MerchantProduct $keptOffer,
        MerchantProduct $absorbedOffer,
    ): Order {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $keptLine = (new OrderLine())
            ->setOrder($order)
            ->setMerchantProduct($keptOffer)
            ->setQuantity(1)
            ->setUnitPriceTnd('1.000')
            ->setLineTotalTnd('1.000')
            ->markPrepared(true);
        $absorbedLine = (new OrderLine())
            ->setOrder($order)
            ->setMerchantProduct($absorbedOffer)
            ->setQuantity(2)
            ->setUnitPriceTnd('2.500')
            ->setLineTotalTnd('5.000');
        $order->addLine($keptLine);
        $order->addLine($absorbedLine);
        $order->recomputeTotal();

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }
}
