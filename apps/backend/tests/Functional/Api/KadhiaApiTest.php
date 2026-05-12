<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Kadhia;
use App\Entity\KadhiaLine;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductReferenceStatus;
use Symfony\Component\Uid\Uuid;

final class KadhiaApiTest extends FunctionalApiTestCase
{
    // GET /api/me/stores/{storeId}/kadhia

    public function testGetKadhiaAutoCreatesEmptyDraft(): void
    {
        $customer = $this->createUser('kadhia-get@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/stores/%s/kadhia', $shop->getId()),
            user: $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('draft', $payload['status']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame([], $payload['lines']);
        self::assertSame('0.000', $payload['total_tnd']);
        self::assertNull($payload['notes']);

        $repo = $this->entityManager->getRepository(Kadhia::class);
        self::assertCount(1, $repo->findAll());
    }

    public function testGetKadhiaReturnsSameDraftOnSecondCall(): void
    {
        $customer = $this->createUser('kadhia-get-idempotent@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $this->requestJson('GET', \sprintf('/api/me/stores/%s/kadhia', $shop->getId()), user: $customer);
        $this->requestJson('GET', \sprintf('/api/me/stores/%s/kadhia', $shop->getId()), user: $customer);

        $repo = $this->entityManager->getRepository(Kadhia::class);
        self::assertCount(1, $repo->findAll());
    }

    public function testGetKadhiaShopNotFoundReturns404(): void
    {
        $customer = $this->createUser('kadhia-get-404@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'GET',
            '/api/me/stores/00000000-0000-0000-0000-000000000099/kadhia',
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetKadhiaInactiveShopReturns404(): void
    {
        $customer = $this->createUser('kadhia-get-inactive@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop(active: false);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/stores/%s/kadhia', $shop->getId()),
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetKadhiaUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/stores/%s/kadhia', $shop->getId()),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    // PUT /api/me/stores/{storeId}/kadhia/lines/{merchantProductId}

    public function testUpsertLineCreatesKadhiaAndLine(): void
    {
        $customer = $this->createUser('kadhia-upsert@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop, '2.500');

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/stores/%s/kadhia/lines/%s', $shop->getId(), $product->getId()),
            ['quantity' => 3],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['lines']);
        self::assertSame(3, $payload['lines'][0]['quantity']);
        self::assertSame('2.500', $payload['lines'][0]['unit_price_tnd']);
        self::assertSame('7.500', $payload['lines'][0]['subtotal_tnd']);
        self::assertSame('7.500', $payload['total_tnd']);
    }

    public function testUpsertLineUpdatesQuantityOnExistingLine(): void
    {
        $customer = $this->createUser('kadhia-upsert-update@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop, '1.000');

        $this->requestJson(
            'PUT',
            \sprintf('/api/me/stores/%s/kadhia/lines/%s', $shop->getId(), $product->getId()),
            ['quantity' => 2],
            $customer,
        );

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/stores/%s/kadhia/lines/%s', $shop->getId(), $product->getId()),
            ['quantity' => 5],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['lines']);
        self::assertSame(5, $payload['lines'][0]['quantity']);

        $repo = $this->entityManager->getRepository(KadhiaLine::class);
        self::assertCount(1, $repo->findAll());
    }

    public function testUpsertLineProductNotFoundReturns404(): void
    {
        $customer = $this->createUser('kadhia-upsert-404@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/stores/%s/kadhia/lines/00000000-0000-0000-0000-000000000099', $shop->getId()),
            ['quantity' => 1],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUpsertLineProductBelongsToOtherShopReturns404(): void
    {
        $customer = $this->createUser('kadhia-upsert-wrong-shop@example.test', ['ROLE_CUSTOMER']);
        $shop1 = $this->createShop();
        $shop2 = $this->createShop();
        $product = $this->createMerchantProduct($shop2, '1.000');

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/stores/%s/kadhia/lines/%s', $shop1->getId(), $product->getId()),
            ['quantity' => 1],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUpsertLineUnavailableProductReturns404(): void
    {
        $customer = $this->createUser('kadhia-upsert-unavail@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop, '1.000', available: false);

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/stores/%s/kadhia/lines/%s', $shop->getId(), $product->getId()),
            ['quantity' => 1],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUpsertLineUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop, '1.000');

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/stores/%s/kadhia/lines/%s', $shop->getId(), $product->getId()),
            ['quantity' => 1],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    // DELETE /api/me/stores/{storeId}/kadhia/lines/{merchantProductId}

    public function testRemoveLineReturns204(): void
    {
        $customer = $this->createUser('kadhia-remove@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop, '1.000');

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $line = (new KadhiaLine())
            ->setKadhia($kadhia)
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd('1.000');
        $this->entityManager->persist($kadhia);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/me/stores/%s/kadhia/lines/%s', $shop->getId(), $product->getId()),
            user: $customer,
        );

        self::assertSame(204, $response->getStatusCode());

        $this->entityManager->clear();
        self::assertCount(0, $this->entityManager->getRepository(KadhiaLine::class)->findAll());
    }

    public function testRemoveLineNotFoundReturns404(): void
    {
        $customer = $this->createUser('kadhia-remove-nline@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop, '1.000');

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/me/stores/%s/kadhia/lines/%s', $shop->getId(), $product->getId()),
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRemoveLineNoKadhiaReturns404(): void
    {
        $customer = $this->createUser('kadhia-remove-nkadhia@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop, '1.000');

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/me/stores/%s/kadhia/lines/%s', $shop->getId(), $product->getId()),
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRemoveLineUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop, '1.000');

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/me/stores/%s/kadhia/lines/%s', $shop->getId(), $product->getId()),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    // PATCH /api/me/stores/{storeId}/kadhia

    public function testPatchNotesUpdatesNote(): void
    {
        $customer = $this->createUser('kadhia-notes@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/stores/%s/kadhia', $shop->getId()),
            ['notes' => 'Sacs séparés SVP'],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('Sacs séparés SVP', $payload['notes']);
    }

    public function testPatchNotesClearsNote(): void
    {
        $customer = $this->createUser('kadhia-notes-clear@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop)->setNotes('old note');
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/stores/%s/kadhia', $shop->getId()),
            ['notes' => null],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertNull($payload['notes']);
    }

    public function testPatchNotesNoKadhiaReturns404(): void
    {
        $customer = $this->createUser('kadhia-notes-404@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/stores/%s/kadhia', $shop->getId()),
            ['notes' => 'test'],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPatchNotesUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/stores/%s/kadhia', $shop->getId()),
            ['notes' => 'test'],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    // Helpers

    private function createMerchantProduct(
        Shop $shop,
        string $priceTnd,
        bool $available = true,
        bool $visible = true,
    ): MerchantProduct {
        $id = Uuid::v4();

        $brand = (new Brand())
            ->setCanonicalName('Marque Test')
            ->setSlug('marque-test-'.$id);
        $this->entityManager->persist($brand);

        $category = (new Category())
            ->setNameFr('Catégorie Test')
            ->setSlug('categorie-test-'.$id);
        $this->entityManager->persist($category);

        $ref = (new ProductReference())
            ->setNameFr('Produit Test '.$id)
            ->setBrand($brand)
            ->setCategory($category)
            ->setStatus(ProductReferenceStatus::Approved);
        $this->entityManager->persist($ref);

        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd($priceTnd)
            ->setAvailable($available)
            ->setVisible($visible);
        $this->entityManager->persist($product);

        $this->entityManager->flush();

        return $product;
    }
}
