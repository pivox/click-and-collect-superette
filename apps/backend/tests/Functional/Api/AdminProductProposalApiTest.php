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
        self::assertCount(2, $payload);
        self::assertArrayHasKey('id', $payload[0]);
        self::assertArrayHasKey('name_fr', $payload[0]);
        self::assertArrayHasKey('status', $payload[0]);
        self::assertArrayHasKey('proposed_by', $payload[0]);
        self::assertArrayHasKey('created_at', $payload[0]);
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
        self::assertCount(1, $payload);
        self::assertSame('pending', $payload[0]['status']);
    }

    public function testAdminCanApproveProposalAndProductReferenceIsCreated(): void
    {
        $admin = $this->createUser('admin-approve@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-approve@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->createCategory('Laits');
        $proposal = $this->createProposal($shop, $merchant, $category, 'Lait frais', brandName: 'BrandApprove');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/admin/product-proposals/%s/approve', $proposal->getId()),
            user: $admin,
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
            'POST',
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

    public function testAdminCanMergeProposalToExistingProductReference(): void
    {
        $admin = $this->createUser('admin-merge@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-merge@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->createCategory('Conserves');
        $proposal = $this->createProposal($shop, $merchant, $category, 'Harissa locale');
        $existingRef = $this->createProductReference('Jouda', $category, 'Harissa');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/admin/product-proposals/%s/merge', $proposal->getId()),
            ['product_reference_id' => $existingRef->getId()->toRfc4122()],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());

        $this->entityManager->clear();
        $updatedProposal = $this->entityManager->getRepository(ProductReferenceProposal::class)->find($proposal->getId());
        self::assertInstanceOf(ProductReferenceProposal::class, $updatedProposal);
        self::assertSame(ProductReferenceProposalStatus::Merged, $updatedProposal->getStatus());
        self::assertNotNull($updatedProposal->getCreatedProductReference());
        self::assertSame($existingRef->getId()->toRfc4122(), $updatedProposal->getCreatedProductReference()->getId()->toRfc4122());
    }

    public function testRejectWithoutReasonReturnsValidationError(): void
    {
        $admin = $this->createUser('admin-reject-empty@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-reject-empty@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->createCategory('Divers');
        $proposal = $this->createProposal($shop, $merchant, $category, 'Produit sans raison');

        $response = $this->requestJson(
            'POST',
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
            'POST',
            \sprintf('/api/admin/product-proposals/%s/approve', $proposal->getId()),
            user: $merchant,
        );
        $rejectResponse = $this->requestJson(
            'POST',
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

    private function createProposal(
        Shop $shop,
        User $proposedBy,
        Category $category,
        string $nameFr,
        ?string $brandName = null,
    ): ProductReferenceProposal {
        $proposal = (new ProductReferenceProposal())
            ->setShop($shop)
            ->setProposedBy($proposedBy)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setUnit(ProductUnit::Piece)
            ->setBrandName($brandName);

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
