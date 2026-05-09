<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Category;
use App\Entity\ProductReferenceProposal;
use App\Enum\ProductReferenceProposalStatus;

final class ProductReferenceProposalApiTest extends FunctionalApiTestCase
{
    public function testMerchantCanCreateProposal(): void
    {
        $merchant = $this->createUser('merchant-proposal-create@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->createCategory('Boissons');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/product-proposals', $shop->getId()),
            [
                'name_fr' => 'Soda local',
                'category_id' => $category->getId()->toRfc4122(),
                'unit' => 'litre',
                'brand_name' => 'BrandTest',
            ],
            $merchant,
        );

        self::assertSame(201, $response->getStatusCode());
    }

    public function testCreatedProposalHasPendingStatus(): void
    {
        $merchant = $this->createUser('merchant-proposal-status@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->createCategory('Épicerie');

        $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/product-proposals', $shop->getId()),
            [
                'name_fr' => 'Produit test',
                'category_id' => $category->getId()->toRfc4122(),
                'unit' => 'piece',
            ],
            $merchant,
        );

        $proposals = $this->entityManager->getRepository(ProductReferenceProposal::class)->findAll();
        self::assertCount(1, $proposals);
        self::assertSame(ProductReferenceProposalStatus::Pending, $proposals[0]->getStatus());
    }

    public function testMerchantCanListHisProposals(): void
    {
        $merchant = $this->createUser('merchant-proposal-list@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->createCategory('Confiseries');

        $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/product-proposals', $shop->getId()),
            [
                'name_fr' => 'Bonbons local',
                'category_id' => $category->getId()->toRfc4122(),
                'unit' => 'piece',
            ],
            $merchant,
        );

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/product-proposals', $shop->getId()),
            user: $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload);
        self::assertSame('Bonbons local', $payload[0]['name_fr']);
        self::assertSame('pending', $payload[0]['status']);
    }

    public function testNonOwnerMerchantIsRefusedOnProposalRoutes(): void
    {
        $owner = $this->createUser('merchant-proposal-owner@example.test', ['ROLE_MERCHANT']);
        $other = $this->createUser('merchant-proposal-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);
        $category = $this->createCategory('Autres');

        $getResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/product-proposals', $shop->getId()),
            user: $other,
        );
        $postResponse = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/product-proposals', $shop->getId()),
            [
                'name_fr' => 'Tentative intruse',
                'category_id' => $category->getId()->toRfc4122(),
                'unit' => 'piece',
            ],
            $other,
        );

        self::assertSame(403, $getResponse->getStatusCode());
        self::assertSame(403, $postResponse->getStatusCode());
    }

    public function testInvalidCategoryIdReturns404(): void
    {
        $merchant = $this->createUser('merchant-proposal-badcat@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/product-proposals', $shop->getId()),
            [
                'name_fr' => 'Produit sans catégorie valide',
                'category_id' => '00000000-0000-0000-0000-000000000000',
                'unit' => 'piece',
            ],
            $merchant,
        );

        self::assertContains($response->getStatusCode(), [404, 422]);
    }

    private function createCategory(string $name): Category
    {
        $suffix = (string) $this->entityManager->getRepository(Category::class)->count([]);
        $category = (new Category())
            ->setNameFr($name)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '').'-'.$suffix);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }
}
