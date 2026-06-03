<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductReference;
use App\Entity\ProductReferenceProposal;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductReferenceProposalStatus;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;

final class AdminProductProposalApiTest extends FunctionalApiTestCase
{
    public function testAdminCanListProposals(): void
    {
        $admin = $this->createUser('admin-list@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-for-admin@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->createCategory('Épicerie');
        $this->createProposal($shop, $merchant, $category, 'Produit A');
        $this->createProposal($shop, $merchant, $category, 'Produit B');

        $response = $this->requestJson('GET', '/api/admin/product-proposals', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayHasKey('items', $payload);
        self::assertArrayHasKey('total', $payload);
        self::assertArrayHasKey('page', $payload);
        self::assertArrayHasKey('limit', $payload);
        self::assertSame(2, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertCount(2, $payload['items']);
        self::assertArrayHasKey('id', $payload['items'][0]);
        self::assertArrayHasKey('name_fr', $payload['items'][0]);
        self::assertArrayHasKey('status', $payload['items'][0]);
        self::assertArrayHasKey('proposed_by', $payload['items'][0]);
        self::assertArrayHasKey('created_at', $payload['items'][0]);
    }

    public function testAdminCanFilterProposalsByStatus(): void
    {
        $admin = $this->createUser('admin-filter@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-filter@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->createCategory('Boissons');
        $this->createProposal($shop, $merchant, $category, 'Produit pending');
        $rejected = $this->createProposal($shop, $merchant, $category, 'Produit rejeté');
        $rejected->setStatus(ProductReferenceProposalStatus::Rejected);
        $rejected->setRejectionReason('Doublon');
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/admin/product-proposals?status=pending', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertCount(1, $payload['items']);
        self::assertSame('pending', $payload['items'][0]['status']);
    }

    public function testListProposalsPagination(): void
    {
        $admin = $this->createUser('admin-pag@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-pag@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->createCategory('Pag');
        for ($i = 1; $i <= 5; ++$i) {
            $this->createProposal($shop, $merchant, $category, \sprintf('Produit %d', $i));
        }

        $response = $this->requestJson('GET', '/api/admin/product-proposals?page=2&limit=2', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(5, $payload['total']);
        self::assertSame(2, $payload['page']);
        self::assertSame(2, $payload['limit']);
        self::assertCount(2, $payload['items']);
    }

    public function testListProposalsInvalidPageReturns400(): void
    {
        $admin = $this->createUser('admin-page-inv@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/product-proposals?page=abc', user: $admin);
        self::assertSame(400, $response->getStatusCode());

        $response = $this->requestJson('GET', '/api/admin/product-proposals?page=0', user: $admin);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testListProposalsInvalidLimitReturns400(): void
    {
        $admin = $this->createUser('admin-limit-inv@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/product-proposals?limit=abc', user: $admin);
        self::assertSame(400, $response->getStatusCode());

        $response = $this->requestJson('GET', '/api/admin/product-proposals?limit=-5', user: $admin);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testAdminCanApproveProposalAndProductReferenceIsCreated(): void
    {
        $admin = $this->createUser('admin-approve@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-approve@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->createCategory('Laits');
        $brand = $this->createBrand('BrandApprove');
        $proposal = $this->createProposal($shop, $merchant, $category, 'Lait frais', brand: $brand);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/approve', $proposal->getId()),
            [],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());

        $this->entityManager->clear();
        $updatedProposal = $this->entityManager->getRepository(ProductReferenceProposal::class)->find($proposal->getId());
        self::assertInstanceOf(ProductReferenceProposal::class, $updatedProposal);
        self::assertSame(ProductReferenceProposalStatus::Approved, $updatedProposal->getStatus());
        self::assertNotNull($updatedProposal->getCreatedProductReference());
        self::assertSame(ProductReferenceStatus::Approved, $updatedProposal->getCreatedProductReference()->getStatus());
        self::assertSame('Lait frais', $updatedProposal->getCreatedProductReference()->getNameFr());
    }

    public function testAdminCanRejectProposalWithReason(): void
    {
        $admin = $this->createUser('admin-reject@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-reject@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->createCategory('Pâtisseries');
        $proposal = $this->createProposal($shop, $merchant, $category, 'Gâteau local');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/reject', $proposal->getId()),
            ['reason' => 'Produit trop générique'],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());

        $this->entityManager->clear();
        $updatedProposal = $this->entityManager->getRepository(ProductReferenceProposal::class)->find($proposal->getId());
        self::assertInstanceOf(ProductReferenceProposal::class, $updatedProposal);
        self::assertSame(ProductReferenceProposalStatus::Rejected, $updatedProposal->getStatus());
        self::assertSame('Produit trop générique', $updatedProposal->getRejectionReason());
    }

    public function testRejectWithoutReasonReturnsValidationError(): void
    {
        $admin = $this->createUser('admin-reject-empty@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-reject-empty@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->createCategory('Divers');
        $proposal = $this->createProposal($shop, $merchant, $category, 'Produit sans raison');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/reject', $proposal->getId()),
            ['reason' => ''],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testMerchantCannotAccessAdminProposalRoutes(): void
    {
        $merchant = $this->createUser('merchant-admin-forbidden@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->createCategory('Autre');
        $proposal = $this->createProposal($shop, $merchant, $category, 'Produit marchand');

        $listResponse = $this->requestJson('GET', '/api/admin/product-proposals', user: $merchant);
        $approveResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/approve', $proposal->getId()),
            [],
            $merchant,
        );
        $rejectResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/reject', $proposal->getId()),
            ['reason' => 'Test'],
            $merchant,
        );

        self::assertSame(403, $listResponse->getStatusCode());
        self::assertSame(403, $approveResponse->getStatusCode());
        self::assertSame(403, $rejectResponse->getStatusCode());
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

    private function createBrand(string $name): Brand
    {
        $suffix = (string) $this->entityManager->getRepository(Brand::class)->count([]);
        $brand = (new Brand())
            ->setCanonicalName($name)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '').'-'.$suffix);

        $this->entityManager->persist($brand);
        $this->entityManager->flush();

        return $brand;
    }

    private function createProposal(
        Shop $shop,
        User $proposedBy,
        Category $category,
        string $nameFr,
        ?Brand $brand = null,
    ): ProductReferenceProposal {
        $proposal = (new ProductReferenceProposal())
            ->setShop($shop)
            ->setProposedBy($proposedBy)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setUnit(ProductUnit::Piece)
            ->setBrand($brand);

        $this->entityManager->persist($proposal);
        $this->entityManager->flush();

        return $proposal;
    }

    private function createProductReference(
        string $brandName,
        Category $category,
        string $nameFr,
        ProductReferenceStatus $status = ProductReferenceStatus::Approved,
    ): ProductReference {
        $suffix = (string) $this->entityManager->getRepository(ProductReference::class)->count([]);
        $brand = (new Brand())
            ->setCanonicalName($brandName)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $brandName) ?? '').'-'.$suffix);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setUnit(ProductUnit::Piece)
            ->setStatus($status);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();

        return $productReference;
    }
}
