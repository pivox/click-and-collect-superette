<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Symfony\Component\Uid\Uuid;

final class AdminPromotionApiTest extends FunctionalApiTestCase
{
    public function testAdminSeesOnlyProductsWithPromotion(): void
    {
        $admin = $this->createUser('admin-promotions-list@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-promotions-list@example.test');
        $shop = $this->createNamedShop($merchant, 'Supérette El Manar');
        $promoted = $this->createPromotedProduct($shop, 'Lait Vitalait 1L', '2.500', $this->tunisDay('tomorrow'));
        $this->createPlainProduct($shop, 'Eau Safia 1.5L');

        $response = $this->requestJson('GET', '/api/admin/promotions', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(20, $payload['limit']);
        self::assertCount(1, $payload['items']);

        $item = $payload['items'][0];
        self::assertSame($promoted->getId()->toRfc4122(), $item['id']);
        self::assertSame('Lait Vitalait 1L', $item['product_name']);
        self::assertSame($shop->getId()->toRfc4122(), $item['store_id']);
        self::assertSame('Supérette El Manar', $item['store_name']);
        self::assertSame($merchant->getId()->toRfc4122(), $item['merchant_id']);
        self::assertSame('merchant-promotions-list@example.test', $item['merchant_email']);
        self::assertSame('3.000', $item['price_tnd']);
        self::assertSame('2.500', $item['promotion_price_tnd']);
        self::assertSame($this->tunisDay('tomorrow')->format('Y-m-d'), $item['promotion_ends_on']);
        self::assertTrue($item['promotion_active']);
        self::assertTrue($item['is_available']);
        self::assertTrue($item['is_visible']);
    }

    public function testFilterByPromotionStatus(): void
    {
        $admin = $this->createUser('admin-promotions-status@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-promotions-status@example.test');
        $shop = $this->createNamedShop($merchant, 'Supérette Statuts');
        $active = $this->createPromotedProduct($shop, 'Promo active', '2.500', $this->tunisDay('tomorrow'));
        $expired = $this->createPromotedProduct($shop, 'Promo expirée', '2.500', $this->tunisDay('yesterday'));

        $allPayload = $this->decodeJson($this->requestJson('GET', '/api/admin/promotions', user: $admin));
        self::assertSame(2, $allPayload['total']);

        $activePayload = $this->decodeJson($this->requestJson('GET', '/api/admin/promotions?promotion_status=active', user: $admin));
        self::assertSame(1, $activePayload['total']);
        self::assertSame($active->getId()->toRfc4122(), $activePayload['items'][0]['id']);
        self::assertTrue($activePayload['items'][0]['promotion_active']);

        $expiredPayload = $this->decodeJson($this->requestJson('GET', '/api/admin/promotions?promotion_status=expired', user: $admin));
        self::assertSame(1, $expiredPayload['total']);
        self::assertSame($expired->getId()->toRfc4122(), $expiredPayload['items'][0]['id']);
        self::assertFalse($expiredPayload['items'][0]['promotion_active']);
    }

    public function testPromotionEndingTodayIsStillActive(): void
    {
        $admin = $this->createUser('admin-promotions-today@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-promotions-today@example.test');
        $shop = $this->createNamedShop($merchant, 'Supérette Borne');
        $endingToday = $this->createPromotedProduct($shop, 'Promo du jour', '2.500', $this->tunisDay('today'));

        $activePayload = $this->decodeJson($this->requestJson('GET', '/api/admin/promotions?promotion_status=active', user: $admin));
        self::assertSame(1, $activePayload['total']);
        self::assertSame($endingToday->getId()->toRfc4122(), $activePayload['items'][0]['id']);
        self::assertTrue($activePayload['items'][0]['promotion_active']);

        $expiredPayload = $this->decodeJson($this->requestJson('GET', '/api/admin/promotions?promotion_status=expired', user: $admin));
        self::assertSame(0, $expiredPayload['total']);
    }

    public function testFilterByStoreAndMerchant(): void
    {
        $admin = $this->createUser('admin-promotions-filters@example.test', ['ROLE_ADMIN']);
        $merchantA = $this->createMerchant('merchant-promotions-a@example.test');
        $merchantB = $this->createMerchant('merchant-promotions-b@example.test');
        $shopA = $this->createNamedShop($merchantA, 'Supérette A');
        $shopB = $this->createNamedShop($merchantB, 'Supérette B');
        $productA = $this->createPromotedProduct($shopA, 'Produit A', '2.500', $this->tunisDay('tomorrow'));
        $productB = $this->createPromotedProduct($shopB, 'Produit B', '2.500', $this->tunisDay('tomorrow'));

        $storePayload = $this->decodeJson($this->requestJson(
            'GET',
            \sprintf('/api/admin/promotions?store_id=%s', $shopA->getId()->toRfc4122()),
            user: $admin,
        ));
        self::assertSame(1, $storePayload['total']);
        self::assertSame($productA->getId()->toRfc4122(), $storePayload['items'][0]['id']);

        $merchantPayload = $this->decodeJson($this->requestJson(
            'GET',
            \sprintf('/api/admin/promotions?merchant_id=%s', $merchantB->getId()->toRfc4122()),
            user: $admin,
        ));
        self::assertSame(1, $merchantPayload['total']);
        self::assertSame($productB->getId()->toRfc4122(), $merchantPayload['items'][0]['id']);

        $unknownId = Uuid::v4()->toRfc4122();
        $emptyPayload = $this->decodeJson($this->requestJson(
            'GET',
            \sprintf('/api/admin/promotions?store_id=%s', $unknownId),
            user: $admin,
        ));
        self::assertSame(0, $emptyPayload['total']);
        self::assertSame([], $emptyPayload['items']);
    }

    public function testInvalidFiltersAndPaginationReturnBadRequest(): void
    {
        $admin = $this->createUser('admin-promotions-invalid@example.test', ['ROLE_ADMIN']);

        self::assertSame(400, $this->requestJson('GET', '/api/admin/promotions?promotion_status=bogus', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/promotions?store_id=not-a-uuid', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/promotions?merchant_id=not-a-uuid', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/promotions?page=abc', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/promotions?page=0', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/promotions?limit=abc', user: $admin)->getStatusCode());
    }

    public function testLimitIsCappedAtFifty(): void
    {
        $admin = $this->createUser('admin-promotions-cap@example.test', ['ROLE_ADMIN']);

        $payload = $this->decodeJson($this->requestJson('GET', '/api/admin/promotions?limit=200', user: $admin));
        self::assertSame(50, $payload['limit']);
    }

    public function testAccessControl(): void
    {
        $customer = $this->createUser('customer-promotions@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createMerchant('merchant-promotions-access@example.test');

        self::assertSame(401, $this->requestJson('GET', '/api/admin/promotions')->getStatusCode());
        self::assertSame(403, $this->requestJson('GET', '/api/admin/promotions', user: $customer)->getStatusCode());
        self::assertSame(403, $this->requestJson('GET', '/api/admin/promotions', user: $merchant)->getStatusCode());
    }

    private function createMerchant(string $email): User
    {
        return $this->createUser($email, ['ROLE_MERCHANT']);
    }

    private function createNamedShop(User $owner, string $name): Shop
    {
        $shop = $this->createShop($owner);
        $shop->setName($name);
        $this->entityManager->flush();

        return $shop;
    }

    /**
     * Returns midnight of the given relative day in the Africa/Tunis timezone,
     * matching the boundary used by MerchantProduct::isPromotionActive().
     */
    private function tunisDay(string $relativeDay): \DateTimeImmutable
    {
        return new \DateTimeImmutable($relativeDay, new \DateTimeZone('Africa/Tunis'));
    }

    private function createPromotedProduct(Shop $shop, string $name, string $promotionPriceTnd, \DateTimeImmutable $promotionEndsOn): MerchantProduct
    {
        $product = $this->createPlainProduct($shop, $name);
        $product
            ->setPromotionPriceTnd($promotionPriceTnd)
            ->setPromotionEndsOn($promotionEndsOn);
        $this->entityManager->flush();

        return $product;
    }

    private function createPlainProduct(Shop $shop, string $name): MerchantProduct
    {
        $id = Uuid::v4()->toRfc4122();
        $brand = (new Brand())
            ->setCanonicalName('Brand '.$id)
            ->setSlug('brand-'.$id)
            ->setActive(true);
        $category = (new Category())
            ->setNameFr('Catégorie '.$id)
            ->setSlug('categorie-'.$id)
            ->setActive(true);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($name)
            ->setUnit(ProductUnit::Piece)
            ->setStatus(ProductReferenceStatus::Approved);
        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('3.000')
            ->setAvailable(true)
            ->setVisible(true);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }
}
