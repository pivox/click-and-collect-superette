<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\OrderStatus;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Symfony\Component\Uid\Uuid;

final class MerchantProductBulkAvailabilityApiTest extends FunctionalApiTestCase
{
    public function testOwnerMerchantCanMarkMultipleProductsUnavailable(): void
    {
        $merchant = $this->createUser('merchant-bulk-unavailable@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $firstProduct = $this->createMerchantProduct($shop, 'Lait Vitalait');
        $secondProduct = $this->createMerchantProduct($shop, 'Eau Safia');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()),
            [
                'merchant_product_ids' => [
                    $firstProduct->getId()->toRfc4122(),
                    $secondProduct->getId()->toRfc4122(),
                ],
                'is_available' => false,
                'merchant_note' => '  Rupture temporaire  ',
            ],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(2, $payload['updated_count']);
        self::assertFalse($payload['is_available']);
        self::assertSame('Rupture temporaire', $payload['merchant_note']);
        self::assertSame([
            $firstProduct->getId()->toRfc4122(),
            $secondProduct->getId()->toRfc4122(),
        ], $payload['merchant_product_ids']);

        $this->entityManager->refresh($firstProduct);
        $this->entityManager->refresh($secondProduct);
        self::assertFalse($firstProduct->isAvailable());
        self::assertFalse($secondProduct->isAvailable());
        self::assertTrue($firstProduct->isVisible());
        self::assertTrue($secondProduct->isVisible());
        self::assertSame('Rupture temporaire', $firstProduct->getMerchantNote());
        self::assertSame('Rupture temporaire', $secondProduct->getMerchantNote());
    }

    public function testOwnerMerchantCanMarkMultipleProductsAvailableAndClearEmptyNote(): void
    {
        $merchant = $this->createUser('merchant-bulk-available@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $firstProduct = $this->createMerchantProduct($shop, 'Lait indisponible', isAvailable: false, merchantNote: 'Rupture');
        $secondProduct = $this->createMerchantProduct($shop, 'Eau indisponible', isAvailable: false, merchantNote: 'Rupture');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()),
            [
                'merchant_product_ids' => [
                    $firstProduct->getId()->toRfc4122(),
                    $secondProduct->getId()->toRfc4122(),
                    $secondProduct->getId()->toRfc4122(),
                ],
                'is_available' => true,
                'merchant_note' => '   ',
            ],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(2, $payload['updated_count']);
        self::assertTrue($payload['is_available']);
        self::assertNull($payload['merchant_note']);

        $this->entityManager->refresh($firstProduct);
        $this->entityManager->refresh($secondProduct);
        self::assertTrue($firstProduct->isAvailable());
        self::assertTrue($secondProduct->isAvailable());
        self::assertNull($firstProduct->getMerchantNote());
        self::assertNull($secondProduct->getMerchantNote());
    }

    public function testBulkAvailabilityValidatesPayload(): void
    {
        $merchant = $this->createUser('merchant-bulk-validation@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop, 'Produit validation');

        $missingIdsResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()),
            ['is_available' => false],
            $merchant,
        );
        $emptyIdsResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()),
            ['merchant_product_ids' => [], 'is_available' => false],
            $merchant,
        );
        $tooManyIdsResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()),
            ['merchant_product_ids' => array_map(static fn (): string => Uuid::v4()->toRfc4122(), range(1, 101)), 'is_available' => false],
            $merchant,
        );
        $invalidUuidResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()),
            ['merchant_product_ids' => ['not-a-uuid'], 'is_available' => false],
            $merchant,
        );
        $missingAvailabilityResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()),
            ['merchant_product_ids' => [$product->getId()->toRfc4122()]],
            $merchant,
        );
        $nonBooleanAvailabilityResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()),
            ['merchant_product_ids' => [$product->getId()->toRfc4122()], 'is_available' => 'false'],
            $merchant,
        );
        $tooLongNoteResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()),
            ['merchant_product_ids' => [$product->getId()->toRfc4122()], 'is_available' => false, 'merchant_note' => str_repeat('a', 256)],
            $merchant,
        );

        self::assertSame(422, $missingIdsResponse->getStatusCode());
        self::assertSame(422, $emptyIdsResponse->getStatusCode());
        self::assertSame(422, $tooManyIdsResponse->getStatusCode());
        self::assertSame(422, $invalidUuidResponse->getStatusCode());
        self::assertSame(422, $missingAvailabilityResponse->getStatusCode());
        self::assertSame(422, $nonBooleanAvailabilityResponse->getStatusCode());
        self::assertSame(422, $tooLongNoteResponse->getStatusCode());

        $persistedProduct = $this->entityManager->getRepository(MerchantProduct::class)->find($product->getId());
        self::assertInstanceOf(MerchantProduct::class, $persistedProduct);
        self::assertTrue($persistedProduct->isAvailable());
        self::assertNull($persistedProduct->getMerchantNote());
    }

    public function testUnknownProductRefusesWholeActionWithoutPartialUpdate(): void
    {
        $merchant = $this->createUser('merchant-bulk-unknown@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop, 'Produit valide');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()),
            [
                'merchant_product_ids' => [$product->getId()->toRfc4122(), Uuid::v4()->toRfc4122()],
                'is_available' => false,
                'merchant_note' => 'Rupture',
            ],
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
        $persistedProduct = $this->entityManager->getRepository(MerchantProduct::class)->find($product->getId());
        self::assertInstanceOf(MerchantProduct::class, $persistedProduct);
        self::assertTrue($persistedProduct->isAvailable());
        self::assertNull($persistedProduct->getMerchantNote());
    }

    public function testOtherShopProductRefusesWholeActionWithoutPartialUpdate(): void
    {
        $merchant = $this->createUser('merchant-bulk-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $otherShop = $this->createShop($this->createUser('merchant-bulk-other-shop@example.test', ['ROLE_MERCHANT']));
        $product = $this->createMerchantProduct($shop, 'Produit supérette A');
        $otherProduct = $this->createMerchantProduct($otherShop, 'Produit supérette B');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()),
            [
                'merchant_product_ids' => [$product->getId()->toRfc4122(), $otherProduct->getId()->toRfc4122()],
                'is_available' => false,
                'merchant_note' => 'Rupture',
            ],
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
        $this->entityManager->refresh($product);
        $this->entityManager->refresh($otherProduct);
        self::assertTrue($product->isAvailable());
        self::assertTrue($otherProduct->isAvailable());
        self::assertNull($product->getMerchantNote());
        self::assertNull($otherProduct->getMerchantNote());
    }

    public function testClientAnonymousAndNonOwnerMerchantAreDenied(): void
    {
        $merchant = $this->createUser('merchant-bulk-access-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-bulk-access-other@example.test', ['ROLE_MERCHANT']);
        $client = $this->createUser('client-bulk-access@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop, 'Produit accès');
        $payload = [
            'merchant_product_ids' => [$product->getId()->toRfc4122()],
            'is_available' => false,
        ];

        $anonymousResponse = $this->requestJson('PATCH', \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()), $payload);
        $clientResponse = $this->requestJson('PATCH', \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()), $payload, $client);
        $otherMerchantResponse = $this->requestJson('PATCH', \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()), $payload, $otherMerchant);
        $unknownShopResponse = $this->requestJson('PATCH', \sprintf('/api/merchant/stores/%s/products/bulk-availability', Uuid::v4()), $payload, $merchant);

        self::assertContains($anonymousResponse->getStatusCode(), [401, 403]);
        self::assertSame(403, $clientResponse->getStatusCode());
        self::assertSame(403, $otherMerchantResponse->getStatusCode());
        self::assertSame(404, $unknownShopResponse->getStatusCode());

        $persistedProduct = $this->entityManager->getRepository(MerchantProduct::class)->find($product->getId());
        self::assertInstanceOf(MerchantProduct::class, $persistedProduct);
        self::assertTrue($persistedProduct->isAvailable());
    }

    public function testBulkAvailabilityDoesNotModifyProductReferenceOrExistingSubmittedOrders(): void
    {
        $merchant = $this->createUser('merchant-bulk-no-side-effect@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('client-bulk-no-side-effect@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Produit stable');
        $product = $this->createMerchantProduct($shop, 'Produit stable', productReference: $productReference);
        $order = $this->createSubmittedOrder($customer, $shop, $product);
        $orderLine = $order->getLines()->first();
        self::assertInstanceOf(OrderLine::class, $orderLine);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()),
            [
                'merchant_product_ids' => [$product->getId()->toRfc4122()],
                'is_available' => false,
                'merchant_note' => 'Rupture temporaire',
            ],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $this->entityManager->refresh($productReference);
        $this->entityManager->refresh($order);
        $this->entityManager->refresh($orderLine);
        self::assertSame('Produit stable', $productReference->getNameFr());
        self::assertSame(OrderStatus::Submitted, $order->getStatus());
        self::assertSame(0, bccomp('1.500', $orderLine->getUnitPriceTnd(), 3));
        self::assertSame(0, bccomp('3.000', $orderLine->getLineTotalTnd(), 3));
        self::assertSame(1, $order->getLines()->count());
    }

    public function testBulkUnavailableProductDisappearsFromPublicCatalogButStaysInMerchantCatalog(): void
    {
        $merchant = $this->createUser('merchant-bulk-public-catalog@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop, 'Produit public');

        $updateResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/stores/%s/products/bulk-availability', $shop->getId()),
            [
                'merchant_product_ids' => [$product->getId()->toRfc4122()],
                'is_available' => false,
                'merchant_note' => 'Rupture',
            ],
            $merchant,
        );
        $publicResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shop->getId()));
        $merchantCatalogResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $merchant);

        self::assertSame(200, $updateResponse->getStatusCode());
        self::assertSame(200, $publicResponse->getStatusCode());
        self::assertSame(200, $merchantCatalogResponse->getStatusCode());

        $publicPayload = $this->decodeJson($publicResponse);
        $merchantPayload = $this->decodeJson($merchantCatalogResponse);
        self::assertSame([], $publicPayload['items']);
        self::assertCount(1, $merchantPayload);
        self::assertSame($product->getId()->toRfc4122(), $merchantPayload[0]['id']);
        self::assertFalse($merchantPayload[0]['is_available']);
        self::assertTrue($merchantPayload[0]['is_visible']);
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

    private function createMerchantProduct(
        Shop $shop,
        string $nameFr,
        bool $isAvailable = true,
        ?string $merchantNote = null,
        ?ProductReference $productReference = null,
    ): MerchantProduct {
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference ?? $this->createProductReference($nameFr))
            ->setPriceTnd('1.500')
            ->setAvailable($isAvailable)
            ->setVisible(true)
            ->setMerchantNote($merchantNote);

        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }

    private function createSubmittedOrder(\App\Entity\User $customer, Shop $shop, MerchantProduct $product): Order
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
