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
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use Symfony\Component\Uid\Uuid;

final class KadhiaReplacementsApiTest extends FunctionalApiTestCase
{
    public function testUnavailableLineGetsSameCategoryAlternatives(): void
    {
        $customer = $this->createUser('customer-repl@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $category = $this->createCategory('laitages');

        $unavailable = $this->createProduct($shop, $category, 'Lait Délice', '2.000');
        $unavailable->setAvailable(false);
        $alternative = $this->createProduct($shop, $category, 'Lait Vitalait', '2.100');
        $otherCategory = $this->createProduct($shop, $this->createCategory('epicerie'), 'Harissa', '2.000');
        $this->entityManager->flush();

        $kadhia = $this->createKadhia($customer, $shop, [$unavailable]);

        $response = $this->requestJson('GET', \sprintf('/api/me/kadhias/%s/replacements', $kadhia->getId()), user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($kadhia->getId()->toRfc4122(), $payload['id']);
        self::assertCount(1, $payload['items']);

        $item = $payload['items'][0];
        self::assertSame($unavailable->getId()->toRfc4122(), $item['merchant_product_id']);
        self::assertSame('Lait Délice', $item['product_name']);
        self::assertSame(2, $item['quantity']);
        self::assertSame([$alternative->getId()->toRfc4122()], array_column($item['alternatives'], 'id'));
        self::assertNotContains($otherCategory->getId()->toRfc4122(), array_column($item['alternatives'], 'id'));
    }

    public function testAlternativesAreCappedAtThreeAndSortedByPriceProximity(): void
    {
        $customer = $this->createUser('customer-repl-cap@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $category = $this->createCategory('laitages');

        $unavailable = $this->createProduct($shop, $category, 'Lait Délice', '2.000');
        $unavailable->setAvailable(false);
        $close = $this->createProduct($shop, $category, 'Lait A', '2.050');
        $this->createProduct($shop, $category, 'Lait B', '2.500');
        $this->createProduct($shop, $category, 'Lait C', '3.000');
        $this->createProduct($shop, $category, 'Lait D', '9.000');
        $this->entityManager->flush();

        $kadhia = $this->createKadhia($customer, $shop, [$unavailable]);

        $response = $this->requestJson('GET', \sprintf('/api/me/kadhias/%s/replacements', $kadhia->getId()), user: $customer);

        $payload = $this->decodeJson($response);
        $alternatives = $payload['items'][0]['alternatives'];
        self::assertCount(3, $alternatives);
        self::assertSame($close->getId()->toRfc4122(), $alternatives[0]['id']);
    }

    public function testLineWithoutAlternativeIsIncludedWithEmptyList(): void
    {
        $customer = $this->createUser('customer-repl-none@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $category = $this->createCategory('laitages');

        $unavailable = $this->createProduct($shop, $category, 'Lait Délice', '2.000');
        $unavailable->setAvailable(false);
        $this->entityManager->flush();

        $kadhia = $this->createKadhia($customer, $shop, [$unavailable]);

        $response = $this->requestJson('GET', \sprintf('/api/me/kadhias/%s/replacements', $kadhia->getId()), user: $customer);

        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertSame([], $payload['items'][0]['alternatives']);
    }

    public function testAvailableLinesAreNotListed(): void
    {
        $customer = $this->createUser('customer-repl-ok@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $category = $this->createCategory('laitages');

        $available = $this->createProduct($shop, $category, 'Lait Délice', '2.000');
        $this->entityManager->flush();

        $kadhia = $this->createKadhia($customer, $shop, [$available]);

        $response = $this->requestJson('GET', \sprintf('/api/me/kadhias/%s/replacements', $kadhia->getId()), user: $customer);

        $payload = $this->decodeJson($response);
        self::assertSame([], $payload['items']);
    }

    public function testAlternativesExcludeProductsAlreadyInKadhia(): void
    {
        $customer = $this->createUser('customer-repl-dup@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $category = $this->createCategory('laitages');

        $unavailable = $this->createProduct($shop, $category, 'Lait Délice', '2.000');
        $unavailable->setAvailable(false);
        $alreadyInKadhia = $this->createProduct($shop, $category, 'Lait Vitalait', '2.100');
        $this->entityManager->flush();

        $kadhia = $this->createKadhia($customer, $shop, [$unavailable, $alreadyInKadhia]);

        $response = $this->requestJson('GET', \sprintf('/api/me/kadhias/%s/replacements', $kadhia->getId()), user: $customer);

        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertSame([], $payload['items'][0]['alternatives']);
    }

    public function testKadhiaOfAnotherCustomerReturns404(): void
    {
        $customer = $this->createUser('customer-repl-own@example.test', ['ROLE_CUSTOMER']);
        $other = $this->createUser('customer-repl-other@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $category = $this->createCategory('laitages');
        $product = $this->createProduct($shop, $category, 'Lait Délice', '2.000');
        $this->entityManager->flush();

        $kadhia = $this->createKadhia($other, $shop, [$product]);

        $response = $this->requestJson('GET', \sprintf('/api/me/kadhias/%s/replacements', $kadhia->getId()), user: $customer);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUnknownKadhiaReturns404(): void
    {
        $customer = $this->createUser('customer-repl-404@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'GET',
            '/api/me/kadhias/550e8400-e29b-41d4-a716-446655440000/replacements',
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson('GET', '/api/me/kadhias/550e8400-e29b-41d4-a716-446655440000/replacements');

        self::assertSame(401, $response->getStatusCode());
    }

    // GET /api/me/kadhias/{kadhiaId} — is_available on lines

    public function testKadhiaDetailExposesLineAvailability(): void
    {
        $customer = $this->createUser('customer-repl-detail@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $category = $this->createCategory('laitages');

        $unavailable = $this->createProduct($shop, $category, 'Lait Délice', '2.000');
        $unavailable->setAvailable(false);
        $available = $this->createProduct($shop, $category, 'Lait Vitalait', '2.100');
        $this->entityManager->flush();

        $kadhia = $this->createKadhia($customer, $shop, [$unavailable, $available]);

        $response = $this->requestJson('GET', \sprintf('/api/me/kadhias/%s', $kadhia->getId()), user: $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        $availabilityByProduct = array_column($payload['lines'], 'is_available', 'merchant_product_id');
        self::assertFalse($availabilityByProduct[$unavailable->getId()->toRfc4122()]);
        self::assertTrue($availabilityByProduct[$available->getId()->toRfc4122()]);
    }

    // Helpers

    private function createCategory(string $slug): Category
    {
        $category = (new Category())
            ->setNameFr(ucfirst($slug))
            ->setSlug($slug.'-'.Uuid::v4()->toRfc4122());
        $this->entityManager->persist($category);

        return $category;
    }

    private function createProduct(Shop $shop, Category $category, string $name, string $price): MerchantProduct
    {
        $id = Uuid::v4()->toRfc4122();
        $brand = (new Brand())
            ->setCanonicalName('Brand '.$id)
            ->setSlug('brand-'.$id);
        $reference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($name)
            ->setStatus(ProductReferenceStatus::Approved);
        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($reference)
            ->setPriceTnd($price);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($reference);
        $this->entityManager->persist($product);

        return $product;
    }

    /**
     * @param list<MerchantProduct> $products
     */
    private function createKadhia(User $customer, Shop $shop, array $products): Kadhia
    {
        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        foreach ($products as $product) {
            $line = (new KadhiaLine())
                ->setMerchantProduct($product)
                ->setQuantity(2)
                ->setUnitPriceTnd($product->getPriceTnd());
            $kadhia->addLine($line);
            $this->entityManager->persist($line);
        }
        $this->entityManager->persist($kadhia);
        $this->entityManager->flush();

        return $kadhia;
    }
}
