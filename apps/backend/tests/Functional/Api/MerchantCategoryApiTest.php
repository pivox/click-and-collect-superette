<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantLocalProduct;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;

final class MerchantCategoryApiTest extends FunctionalApiTestCase
{
    public function testOwnerMerchantCanCreateListPatchAndDeleteCategories(): void
    {
        $merchant = $this->createUser('merchant-category-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/categories', $shop->getId()),
            [
                'name_fr' => 'Rayon frais',
                'name_ar' => 'منتجات طازجة',
                'sort_order' => 10,
                'active' => true,
            ],
            $merchant,
        );

        self::assertSame(201, $createResponse->getStatusCode());
        $created = $this->decodeJson($createResponse);
        self::assertArrayHasKey('id', $created);
        self::assertSame('Rayon frais', $created['name_fr']);
        self::assertSame('rayon-frais', $created['slug']);
        self::assertSame('منتجات طازجة', $created['name_ar']);
        self::assertNull($created['parent_id']);
        self::assertSame(10, $created['sort_order']);
        self::assertTrue($created['active']);

        $listResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/categories', $shop->getId()), user: $merchant);
        self::assertSame(200, $listResponse->getStatusCode());
        $list = $this->decodeJson($listResponse);
        self::assertCount(1, $list);
        self::assertSame($created['id'], $list[0]['id']);

        $patchResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/categories/%s', $created['id']),
            [
                'name_fr' => 'Frais du jour',
                'name_ar' => null,
                'sort_order' => 20,
                'active' => false,
            ],
            $merchant,
        );

        self::assertSame(200, $patchResponse->getStatusCode());
        $patched = $this->decodeJson($patchResponse);
        self::assertSame('Frais du jour', $patched['name_fr']);
        self::assertNull($patched['name_ar']);
        self::assertSame(20, $patched['sort_order']);
        self::assertFalse($patched['active']);

        $deleteResponse = $this->requestJson('DELETE', \sprintf('/api/merchant/categories/%s', $created['id']), user: $merchant);
        self::assertSame(204, $deleteResponse->getStatusCode());

        $afterDeleteResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/categories', $shop->getId()), user: $merchant);
        self::assertSame(200, $afterDeleteResponse->getStatusCode());
        self::assertSame([], $this->decodeJson($afterDeleteResponse));
    }

    public function testNonOwnerClientAnonymousAndAdminAreDenied(): void
    {
        $owner = $this->createUser('merchant-category-real-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-category-other@example.test', ['ROLE_MERCHANT']);
        $client = $this->createUser('client-category@example.test', ['ROLE_CUSTOMER']);
        $admin = $this->createUser('admin-category@example.test', ['ROLE_ADMIN']);
        $shop = $this->createShop($owner);
        $createResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/categories', $shop->getId()),
            ['name_fr' => 'Sécurité'],
            $owner,
        );
        $categoryId = $this->decodeJson($createResponse)['id'];

        self::assertSame(403, $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/categories', $shop->getId()), user: $otherMerchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('POST', \sprintf('/api/merchant/stores/%s/categories', $shop->getId()), ['name_fr' => 'Autre'], $otherMerchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/merchant/categories/%s', $categoryId), ['name_fr' => 'Autre'], $otherMerchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('DELETE', \sprintf('/api/merchant/categories/%s', $categoryId), user: $otherMerchant)->getStatusCode());

        self::assertSame(403, $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/categories', $shop->getId()), user: $client)->getStatusCode());
        self::assertSame(403, $this->requestJson('POST', \sprintf('/api/merchant/stores/%s/categories', $shop->getId()), ['name_fr' => 'Client'], $client)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/merchant/categories/%s', $categoryId), ['name_fr' => 'Client'], $client)->getStatusCode());
        self::assertSame(403, $this->requestJson('DELETE', \sprintf('/api/merchant/categories/%s', $categoryId), user: $client)->getStatusCode());

        self::assertSame(403, $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/categories', $shop->getId()), user: $admin)->getStatusCode());
        self::assertSame(403, $this->requestJson('POST', \sprintf('/api/merchant/stores/%s/categories', $shop->getId()), ['name_fr' => 'Admin'], $admin)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/merchant/categories/%s', $categoryId), ['name_fr' => 'Admin'], $admin)->getStatusCode());
        self::assertSame(403, $this->requestJson('DELETE', \sprintf('/api/merchant/categories/%s', $categoryId), user: $admin)->getStatusCode());

        self::assertContains($this->requestJson('GET', \sprintf('/api/merchant/stores/%s/categories', $shop->getId()))->getStatusCode(), [401, 403]);
        self::assertContains($this->requestJson('POST', \sprintf('/api/merchant/stores/%s/categories', $shop->getId()), ['name_fr' => 'Anon'])->getStatusCode(), [401, 403]);
        self::assertContains($this->requestJson('PATCH', \sprintf('/api/merchant/categories/%s', $categoryId), ['name_fr' => 'Anon'])->getStatusCode(), [401, 403]);
        self::assertContains($this->requestJson('DELETE', \sprintf('/api/merchant/categories/%s', $categoryId))->getStatusCode(), [401, 403]);
    }

    public function testMerchantCanAssignOverrideCategoryAndReturnToReferentialFallback(): void
    {
        $merchant = $this->createUser('merchant-category-assign@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Vitalait', 'Lait & produits laitiers', 'lait-produits-laitiers', 'Lait demi-écrémé');
        $merchantProduct = $this->createMerchantProduct($shop, $productReference);
        $categoryId = $this->createMerchantCategory($shop, $merchant, 'Promotions Ramadan');

        $assignResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()),
            ['merchant_category_id' => $categoryId],
            $merchant,
        );
        self::assertSame(200, $assignResponse->getStatusCode());

        $catalogAfterAssign = $this->decodeJson($this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $merchant));
        self::assertSame($categoryId, $catalogAfterAssign[0]['merchant_category_id']);
        self::assertSame('Promotions Ramadan', $catalogAfterAssign[0]['merchant_category_name']);
        self::assertSame('Promotions Ramadan', $catalogAfterAssign[0]['category']);

        $publicAfterAssign = $this->decodeJson($this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shop->getId())));
        self::assertSame('Promotions Ramadan', $publicAfterAssign['items'][0]['category']);
        self::assertSame('promotions-ramadan', $publicAfterAssign['items'][0]['category_slug']);

        $clearResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()),
            ['merchant_category_id' => null],
            $merchant,
        );
        self::assertSame(200, $clearResponse->getStatusCode());

        $catalogAfterClear = $this->decodeJson($this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $merchant));
        self::assertNull($catalogAfterClear[0]['merchant_category_id']);
        self::assertNull($catalogAfterClear[0]['merchant_category_name']);
        self::assertSame('Lait & produits laitiers', $catalogAfterClear[0]['category']);
    }

    public function testMerchantCanAssignCategoryWhenAddingReferenceProduct(): void
    {
        $merchant = $this->createUser('merchant-category-create-reference@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Boga', 'Boissons', 'boissons', 'Boisson gazeuse');
        $categoryId = $this->createMerchantCategory($shop, $merchant, 'Rayon boissons');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()),
            [
                'product_reference_id' => $productReference->getId()->toRfc4122(),
                'price_tnd' => '2.500',
                'is_available' => true,
                'is_visible' => true,
                'merchant_note' => null,
                'merchant_category_id' => $categoryId,
            ],
            $merchant,
        );

        self::assertSame(201, $response->getStatusCode());
        $catalog = $this->decodeJson($this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $merchant));
        self::assertSame($categoryId, $catalog[0]['merchant_category_id']);
        self::assertSame('Rayon boissons', $catalog[0]['merchant_category_name']);
        self::assertSame('Rayon boissons', $catalog[0]['category']);
    }

    public function testMerchantCanAssignCategoryWhenCreatingLocalProduct(): void
    {
        $merchant = $this->createUser('merchant-category-create-local@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $categoryId = $this->createMerchantCategory($shop, $merchant, 'Produits maison');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/local-products', $shop->getId()),
            [
                'name_fr' => 'Bsissa maison',
                'name_ar' => null,
                'brand_name' => null,
                'volume' => '500',
                'unit' => 'gramme',
                'barcode' => null,
                'default_category_name' => 'Epicerie locale',
                'price_tnd' => '6.900',
                'is_available' => true,
                'is_visible' => true,
                'merchant_note' => null,
                'merchant_category_id' => $categoryId,
            ],
            $merchant,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        $catalog = $this->decodeJson($this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $merchant));
        self::assertSame($payload['merchant_product_id'], $catalog[0]['id']);
        self::assertSame($categoryId, $catalog[0]['merchant_category_id']);
        self::assertSame('Produits maison', $catalog[0]['merchant_category_name']);
        self::assertSame('Produits maison', $catalog[0]['category']);
    }

    public function testMerchantCannotAssignCategoryFromAnotherShop(): void
    {
        $owner = $this->createUser('merchant-category-owner-a@example.test', ['ROLE_MERCHANT']);
        $otherOwner = $this->createUser('merchant-category-owner-b@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);
        $otherShop = $this->createShop($otherOwner);
        $productReference = $this->createProductReference('Safia', 'Eaux', 'eaux', 'Eau minérale');
        $merchantProduct = $this->createMerchantProduct($shop, $productReference);
        $otherCategoryId = $this->createMerchantCategory($otherShop, $otherOwner, 'Autre supérette');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()),
            ['merchant_category_id' => $otherCategoryId],
            $owner,
        );

        self::assertSame(422, $response->getStatusCode());
        $catalog = $this->decodeJson($this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $owner));
        self::assertNull($catalog[0]['merchant_category_id']);
        self::assertSame('Eaux', $catalog[0]['category']);
    }

    public function testDeletingAttachedCategoryClearsOverrideAndRestoresFallback(): void
    {
        $merchant = $this->createUser('merchant-category-soft-delete@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Jouda', 'Conserves', 'conserves', 'Harissa');
        $merchantProduct = $this->createMerchantProduct($shop, $productReference);
        $categoryId = $this->createMerchantCategory($shop, $merchant, 'Favoris client');
        $assignResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()),
            ['merchant_category_id' => $categoryId],
            $merchant,
        );
        self::assertSame(200, $assignResponse->getStatusCode());
        $catalogBeforeDelete = $this->decodeJson($this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $merchant));
        self::assertSame($categoryId, $catalogBeforeDelete[0]['merchant_category_id']);

        $deleteResponse = $this->requestJson('DELETE', \sprintf('/api/merchant/categories/%s', $categoryId), user: $merchant);
        self::assertSame(204, $deleteResponse->getStatusCode());

        $categories = $this->decodeJson($this->requestJson('GET', \sprintf('/api/merchant/stores/%s/categories', $shop->getId()), user: $merchant));
        self::assertSame([], $categories);

        $catalog = $this->decodeJson($this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $merchant));
        self::assertNull($catalog[0]['merchant_category_id']);
        self::assertNull($catalog[0]['merchant_category_name']);
        self::assertSame('Conserves', $catalog[0]['category']);

        $recreateResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/categories', $shop->getId()),
            ['name_fr' => 'Favoris client'],
            $merchant,
        );
        self::assertSame(201, $recreateResponse->getStatusCode());
    }

    public function testLocalProductReturnsToLocalCategoryFallbackWhenOverrideIsCleared(): void
    {
        $merchant = $this->createUser('merchant-category-local-clear@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $merchantProduct = $this->createLocalMerchantProduct($shop, 'Harissa maison', 'Epicerie locale');
        $categoryId = $this->createMerchantCategory($shop, $merchant, 'Produits maison');

        $assignResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()),
            ['merchant_category_id' => $categoryId],
            $merchant,
        );
        self::assertSame(200, $assignResponse->getStatusCode());

        $clearResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()),
            ['merchant_category_id' => null],
            $merchant,
        );
        self::assertSame(200, $clearResponse->getStatusCode());

        $catalog = $this->decodeJson($this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $merchant));
        self::assertNull($catalog[0]['merchant_category_id']);
        self::assertNull($catalog[0]['merchant_category_name']);
        self::assertSame('Epicerie locale', $catalog[0]['category']);
    }

    public function testCreatingCategoryRejectsBlankFrenchNameAfterTrim(): void
    {
        $merchant = $this->createUser('merchant-category-blank-name@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/categories', $shop->getId()),
            ['name_fr' => '   '],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testInactiveCategoryStaysListableButDoesNotOverrideCatalog(): void
    {
        $merchant = $this->createUser('merchant-category-inactive@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Vitalait', 'Lait & produits laitiers', 'lait-produits-laitiers', 'Lait demi-écrémé');
        $merchantProduct = $this->createMerchantProduct($shop, $productReference);
        $categoryId = $this->createMerchantCategory($shop, $merchant, 'Rayon froid');
        self::assertSame(200, $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()),
            ['merchant_category_id' => $categoryId],
            $merchant,
        )->getStatusCode());

        self::assertSame(200, $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/categories/%s', $categoryId),
            ['active' => false],
            $merchant,
        )->getStatusCode());

        $categories = $this->decodeJson($this->requestJson('GET', \sprintf('/api/merchant/stores/%s/categories', $shop->getId()), user: $merchant));
        self::assertCount(1, $categories);
        self::assertSame($categoryId, $categories[0]['id']);
        self::assertFalse($categories[0]['active']);

        $catalog = $this->decodeJson($this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $merchant));
        self::assertNull($catalog[0]['merchant_category_id']);
        self::assertNull($catalog[0]['merchant_category_name']);
        self::assertSame('Lait & produits laitiers', $catalog[0]['category']);
    }

    public function testMerchantCannotAssignInactiveCategoryToProduct(): void
    {
        $merchant = $this->createUser('merchant-category-assign-inactive@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $productReference = $this->createProductReference('Safia', 'Eaux', 'eaux', 'Eau minérale');
        $merchantProduct = $this->createMerchantProduct($shop, $productReference);
        $categoryId = $this->createMerchantCategory($shop, $merchant, 'Ancien rayon');
        self::assertSame(200, $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/categories/%s', $categoryId),
            ['active' => false],
            $merchant,
        )->getStatusCode());

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/catalog/%s', $merchantProduct->getId()),
            ['merchant_category_id' => $categoryId],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testMerchantCategoryRejectsIndirectParentCycle(): void
    {
        $merchant = $this->createUser('merchant-category-cycle@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $parentId = $this->createMerchantCategory($shop, $merchant, 'Parent');
        $childResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/categories', $shop->getId()),
            ['name_fr' => 'Enfant', 'parent_id' => $parentId],
            $merchant,
        );
        self::assertSame(201, $childResponse->getStatusCode());
        $childId = $this->decodeJson($childResponse)['id'];

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/categories/%s', $parentId),
            ['parent_id' => $childId],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testMerchantCategoryRejectsMalformedParentIdOnUpdate(): void
    {
        $merchant = $this->createUser('merchant-category-malformed-parent@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $categoryId = $this->createMerchantCategory($shop, $merchant, 'Rayon caisse');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/categories/%s', $categoryId),
            ['parent_id' => 'not-a-uuid'],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testMerchantCategorySlugsAreStableAndUniquePerShop(): void
    {
        $merchant = $this->createUser('merchant-category-slug@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $firstResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/categories', $shop->getId()),
            ['name_fr' => 'Épicerie'],
            $merchant,
        );
        $secondResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/categories', $shop->getId()),
            ['name_fr' => 'Epicerie'],
            $merchant,
        );
        self::assertSame(201, $firstResponse->getStatusCode());
        self::assertSame(201, $secondResponse->getStatusCode());
        $first = $this->decodeJson($firstResponse);
        $second = $this->decodeJson($secondResponse);
        self::assertSame('epicerie', $first['slug']);
        self::assertSame('epicerie-2', $second['slug']);

        $patchResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/categories/%s', $first['id']),
            ['name_fr' => 'Rayon épicerie'],
            $merchant,
        );

        self::assertSame(200, $patchResponse->getStatusCode());
        self::assertSame('epicerie', $this->decodeJson($patchResponse)['slug']);
    }

    private function createMerchantCategory(Shop $shop, mixed $merchant, string $nameFr): string
    {
        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/categories', $shop->getId()),
            ['name_fr' => $nameFr],
            $merchant,
        );
        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        return $payload['id'];
    }

    private function createProductReference(
        string $brandName,
        string $categoryName,
        string $categorySlug,
        string $nameFr,
        ProductReferenceStatus $status = ProductReferenceStatus::Approved,
    ): ProductReference {
        $suffix = (string) $this->entityManager->getRepository(ProductReference::class)->count([]);
        $brand = (new Brand())
            ->setCanonicalName($brandName)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $brandName)).'-'.$suffix);
        $category = (new Category())
            ->setNameFr($categoryName)
            ->setSlug($categorySlug);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setVolume('1.000')
            ->setUnit(ProductUnit::Litre)
            ->setStatus($status);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();

        return $productReference;
    }

    private function createMerchantProduct(Shop $shop, ProductReference $productReference): MerchantProduct
    {
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('1.500');

        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }

    private function createLocalMerchantProduct(Shop $shop, string $nameFr, string $categoryName): MerchantProduct
    {
        $localProduct = (new MerchantLocalProduct())
            ->setShop($shop)
            ->setNameFr($nameFr)
            ->setDefaultCategoryName($categoryName)
            ->setUnit(ProductUnit::Piece);
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setLocalProduct($localProduct)
            ->setPriceTnd('4.500');

        $this->entityManager->persist($localProduct);
        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }
}
