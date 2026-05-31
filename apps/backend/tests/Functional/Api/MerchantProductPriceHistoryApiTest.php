<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\MerchantProductPriceHistory;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;

final class MerchantProductPriceHistoryApiTest extends FunctionalApiTestCase
{
    public function testCatalogCreationRecordsInitialPriceHistory(): void
    {
        $merchant = $this->createUser('merchant-price-initial@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Lait initial');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()),
            [
                'product_reference_id' => $productReference->getId()->toRfc4122(),
                'price_tnd' => '1.650',
                'is_available' => true,
                'is_visible' => true,
            ],
            $merchant,
        );

        self::assertSame(201, $response->getStatusCode());
        $merchantProduct = $this->entityManager->getRepository(MerchantProduct::class)->findOneForShopAndProductReference($shop, $productReference);
        self::assertInstanceOf(MerchantProduct::class, $merchantProduct);

        $historyResponse = $this->requestJson('GET', \sprintf('/api/merchant/products/%s/price-history', $merchantProduct->getId()), user: $merchant);
        self::assertSame(200, $historyResponse->getStatusCode());
        $payload = $this->decodeJson($historyResponse);

        self::assertSame($merchantProduct->getId()->toRfc4122(), $payload['merchantProductId']);
        self::assertSame('1.650', $payload['currentPrice']);
        self::assertSame('TND', $payload['currency']);
        self::assertCount(1, $payload['priceHistory']);
        self::assertNull($payload['priceHistory'][0]['oldPrice']);
        self::assertSame('1.650', $payload['priceHistory'][0]['newPrice']);
        self::assertSame('initial', $payload['priceHistory'][0]['changeType']);
        self::assertSame('merchant_dashboard', $payload['priceHistory'][0]['source']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['priceHistory'][0]['changedByUserId']);
    }

    public function testMerchantCanUpdatePriceAndReadHistory(): void
    {
        $merchant = $this->createUser('merchant-price-update@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $merchantProduct = $this->createMerchantProduct($shop, 'Lait update', '2.500');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/products/%s/price', $merchantProduct->getId()),
            [
                'price' => '2.700',
                'currency' => 'TND',
                'reason' => 'Ajustement prix fournisseur',
            ],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($merchantProduct->getId()->toRfc4122(), $payload['id']);
        self::assertSame('2.700', $payload['currentPrice']);
        self::assertSame('TND', $payload['currency']);
        self::assertSame('2.500', $payload['lastPriceChange']['oldPrice']);
        self::assertSame('2.700', $payload['lastPriceChange']['newPrice']);
        self::assertSame('manual_update', $payload['lastPriceChange']['changeType']);
        self::assertSame('merchant_dashboard', $payload['lastPriceChange']['source']);
        self::assertSame('Ajustement prix fournisseur', $payload['lastPriceChange']['reason']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['lastPriceChange']['changedByUserId']);

        $this->entityManager->refresh($merchantProduct);
        self::assertSame('2.700', $merchantProduct->getPriceTnd());

        $historyResponse = $this->requestJson('GET', \sprintf('/api/merchant/products/%s/price-history', $merchantProduct->getId()), user: $merchant);
        $historyPayload = $this->decodeJson($historyResponse);
        self::assertSame(200, $historyResponse->getStatusCode());
        self::assertCount(1, $historyPayload['priceHistory']);
        self::assertSame('2.500', $historyPayload['priceHistory'][0]['oldPrice']);
        self::assertSame('2.700', $historyPayload['priceHistory'][0]['newPrice']);
    }

    public function testSamePriceDoesNotCreateDuplicateHistory(): void
    {
        $merchant = $this->createUser('merchant-price-same@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Produit sans doublon');

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()),
            [
                'product_reference_id' => $productReference->getId()->toRfc4122(),
                'price_tnd' => '1.650',
            ],
            $merchant,
        );
        self::assertSame(201, $createResponse->getStatusCode());
        $merchantProduct = $this->entityManager->getRepository(MerchantProduct::class)->findOneForShopAndProductReference($shop, $productReference);
        self::assertInstanceOf(MerchantProduct::class, $merchantProduct);

        $updateResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/products/%s/price', $merchantProduct->getId()),
            ['price' => '1.650', 'currency' => 'TND'],
            $merchant,
        );

        self::assertSame(200, $updateResponse->getStatusCode());
        $payload = $this->decodeJson($updateResponse);
        self::assertNull($payload['lastPriceChange']);
        self::assertSame(1, $this->entityManager->getRepository(MerchantProductPriceHistory::class)->count(['merchantProduct' => $merchantProduct]));
    }

    public function testOtherMerchantIsDeniedButAdminCanReadHistory(): void
    {
        $merchant = $this->createUser('merchant-price-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-price-other@example.test', ['ROLE_MERCHANT']);
        $admin = $this->createUser('admin-price-history@example.test', ['ROLE_ADMIN']);
        $shop = $this->createShop($merchant);
        $merchantProduct = $this->createMerchantProduct($shop, 'Produit admin', '3.000');

        $ownerUpdate = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/products/%s/price', $merchantProduct->getId()),
            ['price' => '3.250', 'currency' => 'TND'],
            $merchant,
        );
        $otherRead = $this->requestJson('GET', \sprintf('/api/merchant/products/%s/price-history', $merchantProduct->getId()), user: $otherMerchant);
        $otherUpdate = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/products/%s/price', $merchantProduct->getId()),
            ['price' => '3.500', 'currency' => 'TND'],
            $otherMerchant,
        );
        $adminRead = $this->requestJson('GET', \sprintf('/api/admin/merchant-products/%s/price-history', $merchantProduct->getId()), user: $admin);

        self::assertSame(200, $ownerUpdate->getStatusCode());
        self::assertSame(403, $otherRead->getStatusCode());
        self::assertSame(403, $otherUpdate->getStatusCode());
        self::assertSame(200, $adminRead->getStatusCode());
        self::assertCount(1, $this->decodeJson($adminRead)['priceHistory']);
    }

    public function testPriceChangeDoesNotModifyExistingOrderLineSnapshot(): void
    {
        $merchant = $this->createUser('merchant-price-order@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('client-price-order@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($merchant);
        $merchantProduct = $this->createMerchantProduct($shop, 'Produit commande', '1.500');
        $order = $this->createSubmittedOrder($customer, $shop, $merchantProduct);
        $orderLine = $order->getLines()->first();
        self::assertInstanceOf(OrderLine::class, $orderLine);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/products/%s/price', $merchantProduct->getId()),
            ['price' => '1.800', 'currency' => 'TND'],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $this->entityManager->refresh($orderLine);
        self::assertSame(0, bccomp('1.500', $orderLine->getUnitPriceTnd(), 3));
        self::assertSame(0, bccomp('3.000', $orderLine->getLineTotalTnd(), 3));
    }

    public function testPriceUpdateValidatesPayload(): void
    {
        $merchant = $this->createUser('merchant-price-validation@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $merchantProduct = $this->createMerchantProduct($shop, 'Produit validation', '1.500');

        $negativePriceResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/products/%s/price', $merchantProduct->getId()),
            ['price' => '-1.000', 'currency' => 'TND'],
            $merchant,
        );
        $emptyCurrencyResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/products/%s/price', $merchantProduct->getId()),
            ['price' => '1.700', 'currency' => ''],
            $merchant,
        );

        self::assertSame(422, $negativePriceResponse->getStatusCode());
        self::assertSame(422, $emptyCurrencyResponse->getStatusCode());
        $persistedMerchantProduct = $this->entityManager->getRepository(MerchantProduct::class)->find($merchantProduct->getId());
        self::assertInstanceOf(MerchantProduct::class, $persistedMerchantProduct);
        self::assertSame('1.500', $persistedMerchantProduct->getPriceTnd());
        self::assertSame(0, $this->entityManager->getRepository(MerchantProductPriceHistory::class)->count(['merchantProduct' => $persistedMerchantProduct]));
    }

    private function createProductReference(string $nameFr): ProductReference
    {
        $suffix = (string) $this->entityManager->getRepository(ProductReference::class)->count([]);
        $brand = (new Brand())
            ->setCanonicalName('Marque '.$suffix)
            ->setSlug('marque-'.$suffix);
        $category = (new Category())
            ->setNameFr('Catégorie '.$suffix)
            ->setSlug('categorie-'.$suffix);
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

    private function createMerchantProduct(Shop $shop, string $nameFr, string $priceTnd): MerchantProduct
    {
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($this->createProductReference($nameFr))
            ->setPriceTnd($priceTnd);

        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }

    private function createSubmittedOrder(User $customer, Shop $shop, MerchantProduct $product): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $orderLine = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(2)
            ->setUnitPriceTnd('1.500')
            ->setLineTotalTnd('3.000');

        $order->addLine($orderLine);
        $order->recomputeTotal();
        $order->submit();

        $this->entityManager->persist($order);
        $this->entityManager->persist($orderLine);
        $this->entityManager->flush();

        return $order;
    }
}
