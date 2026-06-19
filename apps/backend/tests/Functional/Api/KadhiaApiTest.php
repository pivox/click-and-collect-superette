<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Kadhia;
use App\Entity\KadhiaLine;
use App\Entity\KadhiaMember;
use App\Entity\KadhiaShareLink;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\KadhiaStatus;
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

    public function testCreateKadhiaCreatesInitialMember(): void
    {
        $customer = $this->createUser('kadhia-create-member@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        $members = $this->entityManager->getRepository(KadhiaMember::class)->findAll();

        self::assertCount(1, $members);
        self::assertSame($payload['id'], $members[0]->getKadhia()->getId()->toRfc4122());
        self::assertSame($customer->getId()->toRfc4122(), $members[0]->getUser()->getId()->toRfc4122());
        self::assertSame('editor', $members[0]->getRole());
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

    public function testListKadhiasReturnsSharedKadhiasForMember(): void
    {
        $owner = $this->createUser('kadhia-list-shared-owner@example.test', ['ROLE_CUSTOMER']);
        $guest = $this->createUser('kadhia-list-shared-guest@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $owner,
        );
        self::assertSame(201, $createResponse->getStatusCode());
        $created = $this->decodeJson($createResponse);

        $shareResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/share-links', $created['id']),
            user: $owner,
        );
        self::assertSame(200, $shareResponse->getStatusCode());
        $token = $this->extractTokenFromShareUrl($this->decodeJson($shareResponse)['share_url']);

        $joinResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhia-share-links/%s/join', $token),
            user: $guest,
        );
        self::assertSame(200, $joinResponse->getStatusCode());

        $response = $this->requestJson('GET', '/api/me/kadhias', user: $guest);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame($created['id'], $payload['items'][0]['id']);
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

    public function testSharedMemberCanReadKadhiaById(): void
    {
        $owner = $this->createUser('kadhia-get-shared-owner@example.test', ['ROLE_CUSTOMER']);
        $guest = $this->createUser('kadhia-get-shared-guest@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $owner,
        );
        $kadhiaId = $this->decodeJson($createResponse)['id'];

        $shareResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId),
            user: $owner,
        );
        $token = $this->extractTokenFromShareUrl($this->decodeJson($shareResponse)['share_url']);
        $this->requestJson('POST', \sprintf('/api/me/kadhia-share-links/%s/join', $token), user: $guest);

        $response = $this->requestJson('GET', \sprintf('/api/me/kadhias/%s', $kadhiaId), user: $guest);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($kadhiaId, $this->decodeJson($response)['id']);
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

    public function testSharedMemberCanPatchNotes(): void
    {
        $owner = $this->createUser('kadhia-notes-shared-owner@example.test', ['ROLE_CUSTOMER']);
        $guest = $this->createUser('kadhia-notes-shared-guest@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $owner,
        );
        $kadhiaId = $this->decodeJson($createResponse)['id'];
        $shareResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId),
            user: $owner,
        );
        $token = $this->extractTokenFromShareUrl($this->decodeJson($shareResponse)['share_url']);
        $this->requestJson('POST', \sprintf('/api/me/kadhia-share-links/%s/join', $token), user: $guest);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/kadhias/%s', $kadhiaId),
            ['notes' => 'Kadhia partagée'],
            $guest,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Kadhia partagée', $this->decodeJson($response)['notes']);
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

    public function testSharedMemberCanUpsertLine(): void
    {
        $owner = $this->createUser('kadhia-upsert-shared-owner@example.test', ['ROLE_CUSTOMER']);
        $guest = $this->createUser('kadhia-upsert-shared-guest@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop, '2.500');

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $owner,
        );
        $kadhiaId = $this->decodeJson($createResponse)['id'];
        $shareResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId),
            user: $owner,
        );
        $token = $this->extractTokenFromShareUrl($this->decodeJson($shareResponse)['share_url']);
        $this->requestJson('POST', \sprintf('/api/me/kadhia-share-links/%s/join', $token), user: $guest);

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/kadhias/%s/lines/%s', $kadhiaId, $product->getId()),
            ['quantity' => 2],
            $guest,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['lines']);
        self::assertSame(2, $payload['lines'][0]['quantity']);
    }

    public function testUpsertLineSnapshotsActivePromotionPrice(): void
    {
        $customer = $this->createUser('kadhia-upsert-promo@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct(
            shop: $shop,
            priceTnd: '2.500',
            promotionPriceTnd: '1.900',
            promotionEndsOn: (new \DateTimeImmutable('tomorrow', new \DateTimeZone('Africa/Tunis')))->format('Y-m-d'),
        );

        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PUT',
            \sprintf('/api/me/kadhias/%s/lines/%s', $kadhia->getId(), $product->getId()),
            ['quantity' => 2],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['lines']);
        self::assertSame('1.900', $payload['lines'][0]['unit_price_tnd']);
        self::assertSame('3.800', $payload['lines'][0]['subtotal_tnd']);
        self::assertSame('3.800', $payload['total_tnd']);
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

    public function testSharedMemberCanRemoveLine(): void
    {
        $owner = $this->createUser('kadhia-remove-shared-owner@example.test', ['ROLE_CUSTOMER']);
        $guest = $this->createUser('kadhia-remove-shared-guest@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $product = $this->createMerchantProduct($shop, '1.000');

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $owner,
        );
        $kadhiaId = $this->decodeJson($createResponse)['id'];
        $this->requestJson(
            'PUT',
            \sprintf('/api/me/kadhias/%s/lines/%s', $kadhiaId, $product->getId()),
            ['quantity' => 1],
            $owner,
        );
        $shareResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId),
            user: $owner,
        );
        $token = $this->extractTokenFromShareUrl($this->decodeJson($shareResponse)['share_url']);
        $this->requestJson('POST', \sprintf('/api/me/kadhia-share-links/%s/join', $token), user: $guest);

        $response = $this->requestJson(
            'DELETE',
            \sprintf('/api/me/kadhias/%s/lines/%s', $kadhiaId, $product->getId()),
            user: $guest,
        );

        self::assertSame(204, $response->getStatusCode());
    }

    // POST /api/me/kadhias/{kadhiaId}/share-links

    public function testCreateShareLinkReturnsReusableActiveLinkForMember(): void
    {
        $customer = $this->createUser('kadhia-share@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $customer,
        );
        $kadhiaId = $this->decodeJson($createResponse)['id'];

        $first = $this->requestJson('POST', \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId), user: $customer);
        $second = $this->requestJson('POST', \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId), user: $customer);

        self::assertSame(200, $first->getStatusCode());
        self::assertSame(200, $second->getStatusCode());
        $firstPayload = $this->decodeJson($first);
        $secondPayload = $this->decodeJson($second);
        self::assertStringStartsWith('http://localhost:3000/kadhia/share/', $firstPayload['share_url']);
        self::assertSame($firstPayload['share_url'], $secondPayload['share_url']);
        self::assertSame($firstPayload['expires_at'], $secondPayload['expires_at']);
    }

    public function testCreateShareLinkNonMemberReturns404(): void
    {
        $owner = $this->createUser('kadhia-share-owner@example.test', ['ROLE_CUSTOMER']);
        $other = $this->createUser('kadhia-share-other@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $kadhia = (new Kadhia())->setCustomer($owner)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', \sprintf('/api/me/kadhias/%s/share-links', $kadhia->getId()), user: $other);

        self::assertSame(404, $response->getStatusCode());
    }

    // POST /api/me/kadhia-share-links/{token}/join

    public function testJoinShareLinkAddsCustomerAndIsIdempotent(): void
    {
        $owner = $this->createUser('kadhia-join-owner@example.test', ['ROLE_CUSTOMER']);
        $guest = $this->createUser('kadhia-join-guest@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $owner,
        );
        $kadhiaId = $this->decodeJson($createResponse)['id'];
        $shareResponse = $this->requestJson('POST', \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId), user: $owner);
        $token = $this->extractTokenFromShareUrl($this->decodeJson($shareResponse)['share_url']);

        $firstJoin = $this->requestJson('POST', \sprintf('/api/me/kadhia-share-links/%s/join', $token), user: $guest);
        $secondJoin = $this->requestJson('POST', \sprintf('/api/me/kadhia-share-links/%s/join', $token), user: $guest);

        self::assertSame(200, $firstJoin->getStatusCode(), (string) $firstJoin->getContent());
        self::assertSame(200, $secondJoin->getStatusCode());
        self::assertSame([
            'kadhia_id' => $kadhiaId,
            'store_id' => $shop->getId()->toRfc4122(),
            'joined' => true,
        ], $this->decodeJson($firstJoin));
        self::assertFalse($this->decodeJson($secondJoin)['joined']);
    }

    public function testJoinUnknownShareLinkReturns404(): void
    {
        $customer = $this->createUser('kadhia-join-unknown@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('POST', '/api/me/kadhia-share-links/unknown-token/join', user: $customer);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testJoinExpiredShareLinkReturns404(): void
    {
        $owner = $this->createUser('kadhia-join-expired-owner@example.test', ['ROLE_CUSTOMER']);
        $guest = $this->createUser('kadhia-join-expired-guest@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $kadhia = (new Kadhia())->setCustomer($owner)->setShop($shop);
        $link = (new KadhiaShareLink())
            ->setKadhia($kadhia)
            ->setCreatedBy($owner)
            ->setExpiresAt(new \DateTimeImmutable('-1 second'))
            ->setTokenHash(hash('sha256', 'expired-token'));
        $this->entityManager->persist($kadhia);
        $this->entityManager->persist($link);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', '/api/me/kadhia-share-links/expired-token/join', user: $guest);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testJoinShareLinkForSubmittedKadhiaReturns422(): void
    {
        $owner = $this->createUser('kadhia-join-submitted-owner@example.test', ['ROLE_CUSTOMER']);
        $guest = $this->createUser('kadhia-join-submitted-guest@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $owner,
        );
        $kadhiaId = $this->decodeJson($createResponse)['id'];
        $shareResponse = $this->requestJson('POST', \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId), user: $owner);
        $token = $this->extractTokenFromShareUrl($this->decodeJson($shareResponse)['share_url']);

        $kadhia = $this->entityManager->getRepository(Kadhia::class)->find($kadhiaId);
        self::assertInstanceOf(Kadhia::class, $kadhia);
        $kadhia->setStatus(KadhiaStatus::Submitted);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', \sprintf('/api/me/kadhia-share-links/%s/join', $token), user: $guest);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('KADHIA_NOT_SHAREABLE', $this->decodeJson($response)['detail']);
    }

    public function testCreateShareLinkReplacesExpiredActiveLink(): void
    {
        $customer = $this->createUser('kadhia-share-expired@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $customer,
        );
        $kadhiaId = $this->decodeJson($createResponse)['id'];

        $first = $this->requestJson('POST', \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId), user: $customer);
        self::assertSame(200, $first->getStatusCode());
        $firstPayload = $this->decodeJson($first);

        $links = $this->entityManager->getRepository(KadhiaShareLink::class)->findAll();
        self::assertCount(1, $links);
        $links[0]->setExpiresAt(new \DateTimeImmutable('-1 second'));
        $this->entityManager->flush();

        $second = $this->requestJson('POST', \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId), user: $customer);
        self::assertSame(200, $second->getStatusCode());
        $secondPayload = $this->decodeJson($second);

        self::assertNotSame($firstPayload['share_url'], $secondPayload['share_url']);
        $this->entityManager->clear();
        $storedLinks = $this->entityManager->getRepository(KadhiaShareLink::class)->findAll();
        self::assertCount(2, $storedLinks);
        self::assertCount(1, array_filter($storedLinks, static fn (KadhiaShareLink $link): bool => $link->isActive()));
    }

    public function testCreateShareLinkFlushesExpiredActiveLinkBeforeReplacement(): void
    {
        $customer = $this->createUser('kadhia-share-expired-flush@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $customer,
        );
        $kadhiaId = $this->decodeJson($createResponse)['id'];

        $shareResponse = $this->requestJson('POST', \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId), user: $customer);
        self::assertSame(200, $shareResponse->getStatusCode());

        $link = $this->entityManager->getRepository(KadhiaShareLink::class)->findOneBy([]);
        self::assertInstanceOf(KadhiaShareLink::class, $link);
        $link->setExpiresAt(new \DateTimeImmutable('-1 second'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $second = $this->requestJson('POST', \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId), user: $customer);

        self::assertSame(200, $second->getStatusCode(), (string) $second->getContent());
        $this->entityManager->clear();
        $storedLinks = $this->entityManager->getRepository(KadhiaShareLink::class)->findAll();

        self::assertCount(2, $storedLinks);
        self::assertCount(1, array_filter($storedLinks, static fn (KadhiaShareLink $storedLink): bool => $storedLink->isActive()));
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
        ?string $promotionPriceTnd = null,
        ?string $promotionEndsOn = null,
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

        if (null !== $promotionPriceTnd) {
            $product->setPromotionPriceTnd($promotionPriceTnd);
        }
        if (null !== $promotionEndsOn) {
            $product->setPromotionEndsOn(new \DateTimeImmutable($promotionEndsOn, new \DateTimeZone('Africa/Tunis')));
        }

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
            ->setStatus(KadhiaStatus::Submitted);
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

    public function testDeleteKadhiaSharedMemberReturns404AndKeepsKadhia(): void
    {
        $owner = $this->createUser('kadhia-delete-shared-owner@example.test', ['ROLE_CUSTOMER']);
        $guest = $this->createUser('kadhia-delete-shared-guest@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $owner,
        );
        self::assertSame(201, $createResponse->getStatusCode());
        $kadhiaId = $this->decodeJson($createResponse)['id'];

        $shareResponse = $this->requestJson('POST', \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId), user: $owner);
        self::assertSame(200, $shareResponse->getStatusCode());
        $token = $this->extractTokenFromShareUrl($this->decodeJson($shareResponse)['share_url']);
        $joinResponse = $this->requestJson('POST', \sprintf('/api/me/kadhia-share-links/%s/join', $token), user: $guest);
        self::assertSame(200, $joinResponse->getStatusCode());

        $guestDeleteResponse = $this->requestJson('DELETE', \sprintf('/api/me/kadhias/%s', $kadhiaId), user: $guest);

        self::assertSame(404, $guestDeleteResponse->getStatusCode());
        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->getRepository(Kadhia::class)->find($kadhiaId));

        $ownerDeleteResponse = $this->requestJson('DELETE', \sprintf('/api/me/kadhias/%s', $kadhiaId), user: $owner);
        self::assertSame(204, $ownerDeleteResponse->getStatusCode());
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

    private function extractTokenFromShareUrl(string $shareUrl): string
    {
        $path = parse_url($shareUrl, \PHP_URL_PATH);
        self::assertIsString($path);
        $prefix = '/kadhia/share/';
        self::assertStringStartsWith($prefix, $path);

        return substr($path, \strlen($prefix));
    }
}
