<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\MerchantCategory;
use App\Entity\MerchantProduct;
use App\Entity\Shop;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;

final class MerchantProductPackApiTest extends FunctionalApiTestCase
{
    private User $merchant;
    private Shop $shop;
    private MerchantProduct $product1;
    private MerchantProduct $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->createUser('merchant@test.fr', ['ROLE_MERCHANT']);
        $this->shop = $this->createShop($this->merchant, true);

        $category = (new MerchantCategory())
            ->setShop($this->shop)
            ->setNameFr('Petit-déjeuner')
            ->setNameAr('الإفطار')
            ->setSlug('petit-dejeuner');
        $this->entityManager->persist($category);

        $this->product1 = (new MerchantProduct())
            ->setShop($this->shop)
            ->setPriceTnd('2.500')
            ->setAvailable(true)
            ->setVisible(true)
            ->setMerchantCategory($category);
        $this->setLocalProduct($this->product1, 'Pain', 'الخبز');

        $this->product2 = (new MerchantProduct())
            ->setShop($this->shop)
            ->setPriceTnd('1.500')
            ->setAvailable(true)
            ->setVisible(true)
            ->setMerchantCategory($category);
        $this->setLocalProduct($this->product2, 'Beurre', 'الزبدة');

        $this->entityManager->persist($this->product1);
        $this->entityManager->persist($this->product2);
        $this->entityManager->flush();
    }

    public function testMerchantCanCreateProductPack(): void
    {
        $payload = [
            'name_fr' => 'Pack Petit-déjeuner',
            'name_ar' => 'حزمة الإفطار',
            'description' => 'Parfait pour commencer la journée',
            'items' => [
                [
                    'merchant_product_id' => $this->product1->getId()->toRfc4122(),
                    'quantity' => 2,
                ],
                [
                    'merchant_product_id' => $this->product2->getId()->toRfc4122(),
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->requestJson('POST', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs', $payload, $this->merchant);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);

        self::assertSame('Pack Petit-déjeuner', $data['name_fr']);
        self::assertSame('حزمة الإفطار', $data['name_ar']);
        self::assertSame('Parfait pour commencer la journée', $data['description']);
        self::assertTrue($data['is_active']);
        self::assertCount(2, $data['items']);
        self::assertSame('6.500', $data['total_price_tnd']);
    }

    public function testMerchantCanListProductPacks(): void
    {
        $payload = [
            'name_fr' => 'Pack Petit-déjeuner',
            'items' => [
                [
                    'merchant_product_id' => $this->product1->getId()->toRfc4122(),
                    'quantity' => 1,
                ],
            ],
        ];

        $this->requestJson('POST', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs', $payload, $this->merchant);

        $response = $this->requestJson('GET', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs', user: $this->merchant);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);

        self::assertIsArray($data);
        self::assertCount(1, $data);
        self::assertSame('Pack Petit-déjeuner', $data[0]['name_fr']);
    }

    public function testMerchantCanUpdateProductPack(): void
    {
        $createPayload = [
            'name_fr' => 'Pack Original',
            'items' => [
                [
                    'merchant_product_id' => $this->product1->getId()->toRfc4122(),
                    'quantity' => 1,
                ],
            ],
        ];

        $createResponse = $this->requestJson('POST', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs', $createPayload, $this->merchant);
        $packId = $this->decodeJson($createResponse)['id'];

        $updatePayload = [
            'name_fr' => 'Pack Modifié',
            'items' => [
                [
                    'merchant_product_id' => $this->product2->getId()->toRfc4122(),
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->requestJson('PATCH', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs/'.$packId, $updatePayload, $this->merchant);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);

        self::assertSame('Pack Modifié', $data['name_fr']);
        self::assertCount(1, $data['items']);
        self::assertSame('3.000', $data['total_price_tnd']);
    }

    public function testMerchantCanDeleteProductPack(): void
    {
        $payload = [
            'name_fr' => 'Pack À supprimer',
            'items' => [
                [
                    'merchant_product_id' => $this->product1->getId()->toRfc4122(),
                    'quantity' => 1,
                ],
            ],
        ];

        $createResponse = $this->requestJson('POST', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs', $payload, $this->merchant);
        $packId = $this->decodeJson($createResponse)['id'];

        $response = $this->requestJson('DELETE', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs/'.$packId, user: $this->merchant);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $getResponse = $this->requestJson('GET', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs/'.$packId, user: $this->merchant);
        self::assertSame(Response::HTTP_NOT_FOUND, $getResponse->getStatusCode());
    }

    public function testClientCanSeeActivePacksPublicly(): void
    {
        $payload = [
            'name_fr' => 'Pack Public',
            'items' => [
                [
                    'merchant_product_id' => $this->product1->getId()->toRfc4122(),
                    'quantity' => 1,
                ],
            ],
        ];

        $this->requestJson('POST', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs', $payload, $this->merchant);

        $response = $this->requestJson('GET', '/api/stores/'.$this->shop->getId()->toRfc4122().'/packs');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);

        self::assertCount(1, $data);
        self::assertSame('Pack Public', $data[0]['name_fr']);
    }

    public function testClientCanAddPackToKadhia(): void
    {
        $payload = [
            'name_fr' => 'Pack Déjeuner',
            'items' => [
                [
                    'merchant_product_id' => $this->product1->getId()->toRfc4122(),
                    'quantity' => 2,
                ],
                [
                    'merchant_product_id' => $this->product2->getId()->toRfc4122(),
                    'quantity' => 1,
                ],
            ],
        ];

        $packResponse = $this->requestJson('POST', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs', $payload, $this->merchant);
        $packId = $this->decodeJson($packResponse)['id'];

        $customer = $this->createUser('customer@test.fr', ['ROLE_CUSTOMER']);
        $kadhia = $this->createKadhia($customer, $this->shop);

        $response = $this->requestJson('POST', '/api/me/kadhias/'.$kadhia->getId()->toRfc4122().'/packs/'.$packId, user: $customer);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);

        self::assertCount(2, $data['lines']);
        self::assertSame(2, $data['lines'][0]['quantity']);
        self::assertSame(1, $data['lines'][1]['quantity']);
    }

    public function testNonOwnerMerchantCannotCreatePackForAnotherShop(): void
    {
        $otherMerchant = $this->createUser('other-merchant@test.fr', ['ROLE_MERCHANT']);
        $otherShop = $this->createShop($otherMerchant, true);

        $payload = [
            'name_fr' => 'Pack Invalide',
            'items' => [
                [
                    'merchant_product_id' => $this->product1->getId()->toRfc4122(),
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->requestJson('POST', '/api/merchant/stores/'.$otherShop->getId()->toRfc4122().'/packs', $payload, $this->merchant);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testAnonymousUserCannotCreatePack(): void
    {
        $payload = [
            'name_fr' => 'Pack Invalide',
            'items' => [
                [
                    'merchant_product_id' => $this->product1->getId()->toRfc4122(),
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->requestJson('POST', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs', $payload);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testValidationFailsWithBlankNameFr(): void
    {
        $payload = [
            'name_fr' => '   ',
            'items' => [
                [
                    'merchant_product_id' => $this->product1->getId()->toRfc4122(),
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->requestJson('POST', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs', $payload, $this->merchant);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testValidationFailsWithEmptyItems(): void
    {
        $payload = [
            'name_fr' => 'Pack Vide',
            'items' => [],
        ];

        $response = $this->requestJson('POST', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs', $payload, $this->merchant);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testValidationFailsWithInvalidQuantity(): void
    {
        $payload = [
            'name_fr' => 'Pack Invalide',
            'items' => [
                [
                    'merchant_product_id' => $this->product1->getId()->toRfc4122(),
                    'quantity' => 0,
                ],
            ],
        ];

        $response = $this->requestJson('POST', '/api/merchant/stores/'.$this->shop->getId()->toRfc4122().'/packs', $payload, $this->merchant);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    private function setLocalProduct(MerchantProduct $product, string $nameFr, string $nameAr): void
    {
        $localProduct = new \App\Entity\MerchantLocalProduct();
        $localProduct->setShop($product->getShop());
        $localProduct->setNameFr($nameFr);
        $localProduct->setNameAr($nameAr);
        $localProduct->setPackQuantity(1);

        $this->entityManager->persist($localProduct);
        $product->setLocalProduct($localProduct);
    }

    private function createKadhia(User $customer, Shop $shop): \App\Entity\Kadhia
    {
        $kadhia = (new \App\Entity\Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        return $kadhia;
    }
}
