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
    // POST /api/me/stores/{storeId}/kadhias

    public function testCreateKadhiaReturns201(): void
    {
        $customer = $this->createUser('kadhia-create@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('draft', $payload['status']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame([], $payload['lines']);
        self::assertSame('0.000', $payload['total_tnd']);
        self::assertNull($payload['notes']);
        self::assertArrayHasKey('id', $payload);
    }

    public function testCreateKadhiaWithNotesReturns201(): void
    {
        $customer = $this->createUser('kadhia-create-notes@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            ['notes' => 'Sacs séparés SVP'],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('Sacs séparés SVP', $payload['notes']);
    }

    public function testCreateKadhiaAllowsMultiplePerShop(): void
    {
        $customer = $this->createUser('kadhia-multi@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $this->requestJson('POST', \sprintf('/api/me/stores/%s/kadhias', $shop->getId()), [], $customer);
        $this->requestJson('POST', \sprintf('/api/me/stores/%s/kadhias', $shop->getId()), [], $customer);

        $repo = $this->entityManager->getRepository(Kadhia::class);
        self::assertCount(2, $repo->findAll());
    }

    public function testCreateKadhiaInactiveShopReturns404(): void
    {
        $customer = $this->createUser('kadhia-create-inactive@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop(active: false);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testCreateKadhiaShopNotFoundReturns404(): void
    {
        $customer = $this->createUser('kadhia-create-404@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'POST',
            '/api/me/stores/00000000-0000-0000-0000-000000000099/kadhias',
            [],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testCreateKadhiaUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    // GET /api/me/kadhias

    public function testListKadhiasReturnsPagedResult(): void
    {
        $customer = $this->createUser('kadhia-list@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        // Create via POST so the kadhia is committed in the same request cycle
        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $customer,
        );
        self::assertSame(201, $createResponse->getStatusCode());
        $created = $this->decodeJson($createResponse);

        $response = $this->requestJson('GET', '/api/me/kadhias', user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(20, $payload['per_page']);
        self::assertCount(1, $payload['items']);
        self::assertSame($created['id'], $payload['items'][0]['id']);
    }

    public function testListKadhiasDoesNotReturnOtherCustomerKadhias(): void
    {
        $customer1 = $this->createUser('kadhia-list-c1@example.test', ['ROLE_CUSTOMER']);
        $customer2 = $this->createUser('kadhia-list-c2@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $kadhia = (new Kadhia())->setCustomer($customer2)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/me/kadhias', user: $customer1);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(0, $payload['total']);
        self::assertCount(0, $payload['items']);
    }

    public function testListKadhiasUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson('GET', '/api/me/kadhias');
        self::assertSame(401, $response->getStatusCode());
    }

    // GET /api/me/kadhias/{kadhiaId}

    public function testGetKadhiaByIdReturns200(): void
    {
        $customer = $this->createUser('kadhia-get-id@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/kadhias/%s', $kadhia->getId()),
            user: $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('draft', $payload['status']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertNull($payload['order_id']);
        self::assertSame([], $payload['lines']);
        self::assertSame('0.000', $payload['total_tnd']);
    }

    public function testGetKadhiaByIdNotFoundReturns404(): void
    {
        $customer = $this->createUser('kadhia-get-id-404@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'GET',
            '/api/me/kadhias/00000000-0000-0000-0000-000000000099',
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetKadhiaByIdAnotherUserReturns404(): void
    {
        $customer1 = $this->createUser('kadhia-get-id-own1@example.test', ['ROLE_CUSTOMER']);
        $customer2 = $this->createUser('kadhia-get-id-own2@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $kadhia = (new Kadhia())->setCustomer($customer1)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/kadhias/%s', $kadhia->getId()),
            user: $customer2,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetKadhiaByIdUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson(
            'GET',
            '/api/me/kadhias/00000000-0000-0000-0000-000000000001',
        );

        self::assertSame(401, $response->getStatusCode());
    }

    // GET /api/me/stores/{storeId}/kadhias

    public function testGetKadhiasByStoreReturnsOwnKadhias(): void
    {
        $customer = $this->createUser('kadhia-bystore@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            user: $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertCount(1, $payload['items']);
        self::assertSame($kadhia->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['items'][0]['store_id']);
        self::assertSame('draft', $payload['items'][0]['status']);
    }

    public function testGetKadhiasByStoreDoesNotReturnOtherCustomerKadhias(): void
    {
        $customer1 = $this->createUser('kadhia-bystore-c1@example.test', ['ROLE_CUSTOMER']);
        $customer2 = $this->createUser('kadhia-bystore-c2@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $kadhia = (new Kadhia())->setCustomer($customer2)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            user: $customer1,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(0, $payload['total']);
        self::assertCount(0, $payload['items']);
    }

    public function testGetKadhiasByStoreUnauthenticatedReturns401(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    // PATCH /api/me/kadhias/{kadhiaId}

    public function testPatchNotesUpdatesNote(): void
    {
        $customer = $this->createUser('kadhia-notes@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/kadhias/%s', $kadhia->getId()),
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
            \sprintf('/api/me/kadhias/%s', $kadhia->getId()),
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

        $response = $this->requestJson(
            'PATCH',
            '/api/me/kadhias/00000000-0000-0000-0000-000000000099',
            ['notes' => 'test'],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPatchNotesUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson(
            'PATCH',
            '/api/me/kadhias/00000000-0000-0000-0000-000000000001',
            ['notes' => 'test'],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    // PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}

    public function testUpsertLineCreatesLine(): void
    {
        $customer = $this->createUser('kadhia-upsert@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop, '2.500');

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/kadhias/%s/lines/%s', $kadhia->getId(), $product->getId()),
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

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $this->requestJson(
            'PUT',
            \sprintf('/api/me/kadhias/%s/lines/%s', $kadhia->getId(), $product->getId()),
            ['quantity' => 2],
            $customer,
        );

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/kadhias/%s/lines/%s', $kadhia->getId(), $product->getId()),
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

    public function testUpsertLineKadhiaNotFoundReturns404(): void
    {
        $customer = $this->createUser('kadhia-upsert-nok@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop, '1.000');

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/kadhias/00000000-0000-0000-0000-000000000099/lines/%s', $product->getId()),
            ['quantity' => 1],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUpsertLineProductNotFoundReturns404(): void
    {
        $customer = $this->createUser('kadhia-upsert-404@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/kadhias/%s/lines/00000000-0000-0000-0000-000000000099', $kadhia->getId()),
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

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop1);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/kadhias/%s/lines/%s', $kadhia->getId(), $product->getId()),
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

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/kadhias/%s/lines/%s', $kadhia->getId(), $product->getId()),
            ['quantity' => 1],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUpsertLineUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson(
            'PUT',
            '/api/me/kadhias/00000000-0000-0000-0000-000000000001/lines/00000000-0000-0000-0000-000000000002',
            ['quantity' => 1],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    // DELETE /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}

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
            \sprintf('/api/me/kadhias/%s/lines/%s', $kadhia->getId(), $product->getId()),
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
            \sprintf('/api/me/kadhias/%s/lines/%s', $kadhia->getId(), $product->getId()),
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
            \sprintf('/api/me/kadhias/00000000-0000-0000-0000-000000000099/lines/%s', $product->getId()),
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRemoveLineUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson(
            'DELETE',
            '/api/me/kadhias/00000000-0000-0000-0000-000000000001/lines/00000000-0000-0000-0000-000000000002',
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

    // DELETE /api/me/kadhias/{kadhiaId}

    public function testDeleteDraftKadhiaReturns204(): void
    {
        $customer = $this->createUser('kadhia-delete@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();
        $kadhiaId = $kadhia->getId()->toRfc4122();

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/me/kadhias/%s', $kadhiaId),
            user: $customer,
        );

        self::assertSame(204, $response->getStatusCode());
        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Kadhia::class)->find($kadhiaId));
    }

    public function testDeleteKadhiaDeletesItsLines(): void
    {
        $customer = $this->createUser('kadhia-delete-lines@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop, '1.500');

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $line = (new KadhiaLine())
            ->setMerchantProduct($product)
            ->setQuantity(2)
            ->setUnitPriceTnd('1.500');
        $kadhia->addLine($line);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();
        $kadhiaId = $kadhia->getId()->toRfc4122();

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/me/kadhias/%s', $kadhiaId),
            user: $customer,
        );

        self::assertSame(204, $response->getStatusCode());
        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Kadhia::class)->find($kadhiaId));
        self::assertCount(0, $this->entityManager->getRepository(KadhiaLine::class)->findAll());
    }

    public function testDeleteSubmittedKadhiaReturns422(): void
    {
        $customer = $this->createUser('kadhia-delete-submitted@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $kadhia = (new Kadhia())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setStatus(\App\Enum\KadhiaStatus::Submitted);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/me/kadhias/%s', $kadhia->getId()),
            user: $customer,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testDeleteKadhiaNotFoundReturns404(): void
    {
        $customer = $this->createUser('kadhia-delete-404@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'DELETE',
            '/api/me/kadhias/550e8400-e29b-41d4-a716-446655440000',
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeleteKadhiaOtherCustomerReturns404(): void
    {
        $owner = $this->createUser('kadhia-delete-owner@example.test', ['ROLE_CUSTOMER']);
        $other = $this->createUser('kadhia-delete-other@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $kadhia = (new Kadhia())->setCustomer($owner)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/me/kadhias/%s', $kadhia->getId()),
            user: $other,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeleteKadhiaUnauthenticatedReturns401(): void
    {
        $customer = $this->createUser('kadhia-delete-unauth@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/me/kadhias/%s', $kadhia->getId()),
        );

        self::assertSame(401, $response->getStatusCode());
    }
}
