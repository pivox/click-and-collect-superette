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

final class AdminProductProposalValidationApiTest extends FunctionalApiTestCase
{
    // --- collection list ---

    public function testAdminListsProposalsPaginated(): void
    {
        $admin = $this->createUser('admin-list-p@test.dev', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-list-p@test.dev', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->makeCategory('Épicerie');

        for ($i = 1; $i <= 3; ++$i) {
            $this->makeProposal($shop, $merchant, $category, 'Produit '.$i);
        }

        $response = $this->requestJson('GET', '/api/admin/product-proposals?page=1&limit=2', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(2, $payload);
        self::assertArrayHasKey('id', $payload[0]);
        self::assertArrayHasKey('name_fr', $payload[0]);
        self::assertArrayHasKey('status', $payload[0]);
        self::assertArrayHasKey('proposed_by', $payload[0]);
        self::assertArrayHasKey('created_at', $payload[0]);
    }

    public function testAdminFiltersByStatusPending(): void
    {
        $admin = $this->createUser('admin-filter-p@test.dev', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-filter-p@test.dev', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->makeCategory('Boissons');

        $this->makeProposal($shop, $merchant, $category, 'En attente');
        $rejected = $this->makeProposal($shop, $merchant, $category, 'Rejeté');
        $rejected->setStatus(ProductReferenceProposalStatus::Rejected)->setRejectionReason('Doublon');
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/admin/product-proposals?status=pending', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload);
        self::assertSame('pending', $payload[0]['status']);
    }

    public function testInvalidStatusFilterReturns400(): void
    {
        $admin = $this->createUser('admin-bad-filter@test.dev', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/product-proposals?status=invalid_value', user: $admin);

        self::assertSame(400, $response->getStatusCode());
    }

    // --- item detail ---

    public function testAdminReadsProposalDetail(): void
    {
        $admin = $this->createUser('admin-detail@test.dev', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-detail@test.dev', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->makeCategory('Détail');
        $proposal = $this->makeProposal($shop, $merchant, $category, 'Produit détail');

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/admin/product-proposals/%s', $proposal->getId()),
            user: $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($proposal->getId()->toRfc4122(), $payload['id']);
        self::assertSame('Produit détail', $payload['name_fr']);
        self::assertSame('pending', $payload['status']);
        self::assertArrayNotHasKey('created_product_reference_id', $payload);
    }

    public function testMissingProposalDetailReturns404(): void
    {
        $admin = $this->createUser('admin-404-detail@test.dev', ['ROLE_ADMIN']);

        $response = $this->requestJson(
            'GET',
            '/api/admin/product-proposals/00000000-0000-0000-0000-000000000000',
            user: $admin,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    // --- approve nominal: creates ProductReference ---

    public function testApproveCreatesProductReference(): void
    {
        $admin = $this->createUser('admin-approve-create@test.dev', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-approve-create@test.dev', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->makeCategory('Laits');
        $brand = $this->makeBrand('Centrale');
        $proposal = $this->makeProposal($shop, $merchant, $category, 'Lait entier', brand: $brand);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/approve', $proposal->getId()),
            [],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());

        $this->entityManager->clear();
        $updated = $this->entityManager->find(ProductReferenceProposal::class, $proposal->getId());
        self::assertInstanceOf(ProductReferenceProposal::class, $updated);
        self::assertSame(ProductReferenceProposalStatus::Approved, $updated->getStatus());
        self::assertNotNull($updated->getCreatedProductReference());
        self::assertSame('Lait entier', $updated->getCreatedProductReference()->getNameFr());
        self::assertSame(ProductReferenceStatus::Approved, $updated->getCreatedProductReference()->getStatus());
    }

    // --- approve with canonicalData ---

    public function testApproveWithCanonicalDataOverridesProposalFields(): void
    {
        $admin = $this->createUser('admin-approve-canonical@test.dev', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-approve-canonical@test.dev', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->makeCategory('Conserves');
        $brand = $this->makeBrand('Unilever');
        $proposal = $this->makeProposal($shop, $merchant, $category, 'Harissa basique');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/approve', $proposal->getId()),
            [
                'canonicalData' => [
                    'nameFr' => 'Harissa Doux 200g',
                    'nameAr' => 'هريسة',
                    'brandId' => $brand->getId()->toRfc4122(),
                    'categoryId' => $category->getId()->toRfc4122(),
                    'unit' => 'piece',
                    'barcode' => '6194003415963',
                ],
            ],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());

        $this->entityManager->clear();
        $updated = $this->entityManager->find(ProductReferenceProposal::class, $proposal->getId());
        self::assertInstanceOf(ProductReferenceProposal::class, $updated);
        self::assertSame(ProductReferenceProposalStatus::Approved, $updated->getStatus());
        $ref = $updated->getCreatedProductReference();
        self::assertNotNull($ref);
        self::assertSame('Harissa Doux 200g', $ref->getNameFr());
        self::assertSame('هريسة', $ref->getNameAr());
        self::assertSame('6194003415963', $ref->getBarcode());
    }

    // --- approve: link to existing ProductReference ---

    public function testApproveLinkToExistingProductReference(): void
    {
        $admin = $this->createUser('admin-approve-link@test.dev', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-approve-link@test.dev', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->makeCategory('Pâtisseries');
        $proposal = $this->makeProposal($shop, $merchant, $category, 'Makroud local');
        $existingRef = $this->makeProductReference('Tunis', $category, 'Makroud');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/approve', $proposal->getId()),
            ['productReferenceId' => $existingRef->getId()->toRfc4122()],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());

        $this->entityManager->clear();
        $updated = $this->entityManager->find(ProductReferenceProposal::class, $proposal->getId());
        self::assertInstanceOf(ProductReferenceProposal::class, $updated);
        self::assertSame(ProductReferenceProposalStatus::Approved, $updated->getStatus());
        self::assertSame($existingRef->getId()->toRfc4122(), $updated->getCreatedProductReference()?->getId()->toRfc4122());
    }

    // --- reject nominal ---

    public function testRejectSavesReason(): void
    {
        $admin = $this->createUser('admin-reject-p@test.dev', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-reject-p@test.dev', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->makeCategory('Divers');
        $proposal = $this->makeProposal($shop, $merchant, $category, 'Produit hors scope');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/reject', $proposal->getId()),
            ['reason' => 'Doublon avec Produit X'],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());

        $this->entityManager->clear();
        $updated = $this->entityManager->find(ProductReferenceProposal::class, $proposal->getId());
        self::assertInstanceOf(ProductReferenceProposal::class, $updated);
        self::assertSame(ProductReferenceProposalStatus::Rejected, $updated->getStatus());
        self::assertSame('Doublon avec Produit X', $updated->getRejectionReason());
    }

    // --- 404 ---

    public function testApproveNonExistentProposalReturns404(): void
    {
        $admin = $this->createUser('admin-approve-404@test.dev', ['ROLE_ADMIN']);

        $response = $this->requestJson(
            'PATCH',
            '/api/admin/product-proposals/00000000-0000-0000-0000-000000000000/approve',
            [],
            $admin,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRejectNonExistentProposalReturns404(): void
    {
        $admin = $this->createUser('admin-reject-404@test.dev', ['ROLE_ADMIN']);

        $response = $this->requestJson(
            'PATCH',
            '/api/admin/product-proposals/00000000-0000-0000-0000-000000000000/reject',
            ['reason' => 'Doublon'],
            $admin,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    // --- 409 already processed ---

    public function testApproveAlreadyApprovedReturns409(): void
    {
        $admin = $this->createUser('admin-approve-409@test.dev', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-approve-409@test.dev', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->makeCategory('Traité');
        $brand = $this->makeBrand('BrandX');
        $proposal = $this->makeProposal($shop, $merchant, $category, 'Déjà approuvé', brand: $brand);
        $proposal->setStatus(ProductReferenceProposalStatus::Approved);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/approve', $proposal->getId()),
            [],
            $admin,
        );

        self::assertSame(409, $response->getStatusCode());
    }

    public function testRejectAlreadyRejectedReturns409(): void
    {
        $admin = $this->createUser('admin-reject-409@test.dev', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-reject-409@test.dev', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->makeCategory('Rejeté');
        $proposal = $this->makeProposal($shop, $merchant, $category, 'Déjà rejeté');
        $proposal->setStatus(ProductReferenceProposalStatus::Rejected)->setRejectionReason('Hors scope');
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/reject', $proposal->getId()),
            ['reason' => 'Autre raison'],
            $admin,
        );

        self::assertSame(409, $response->getStatusCode());
    }

    // --- 401 anonyme / 403 merchant / 403 customer ---

    public function testAnonymousCannotListProposals(): void
    {
        $response = $this->requestJson('GET', '/api/admin/product-proposals');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testMerchantCannotListProposals(): void
    {
        $merchant = $this->createUser('merchant-forbid-list@test.dev', ['ROLE_MERCHANT']);

        $response = $this->requestJson('GET', '/api/admin/product-proposals', user: $merchant);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testCustomerCannotApproveOrReject(): void
    {
        $customer = $this->createUser('customer-forbid@test.dev', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-for-cust@test.dev', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->makeCategory('CustForbid');
        $proposal = $this->makeProposal($shop, $merchant, $category, 'Produit');

        $approveResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/approve', $proposal->getId()),
            [],
            $customer,
        );
        $rejectResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/reject', $proposal->getId()),
            ['reason' => 'Test'],
            $customer,
        );

        self::assertSame(403, $approveResponse->getStatusCode());
        self::assertSame(403, $rejectResponse->getStatusCode());
    }

    // --- 422 payload invalide ---

    public function testRejectWithEmptyReasonReturns422(): void
    {
        $admin = $this->createUser('admin-reject-422@test.dev', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-reject-422@test.dev', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->makeCategory('Validation');
        $proposal = $this->makeProposal($shop, $merchant, $category, 'Produit 422');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/reject', $proposal->getId()),
            ['reason' => ''],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testApproveWithInvalidProductReferenceUuidReturns422(): void
    {
        $admin = $this->createUser('admin-approve-bad-uuid@test.dev', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-approve-bad-uuid@test.dev', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $category = $this->makeCategory('BadUuid');
        $proposal = $this->makeProposal($shop, $merchant, $category, 'Produit bad uuid');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-proposals/%s/approve', $proposal->getId()),
            ['productReferenceId' => 'not-a-valid-uuid'],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    // --- non-regression ProductReference admin (S5-007) ---

    public function testProductReferenceAdminListStillWorks(): void
    {
        $admin = $this->createUser('admin-ref-smoke@test.dev', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/product-references', user: $admin);

        self::assertSame(200, $response->getStatusCode());
    }

    // --- helpers ---

    private function makeCategory(string $name): Category
    {
        $suffix = uniqid('', true);
        $category = (new Category())
            ->setNameFr($name)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '').$suffix);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function makeBrand(string $name): Brand
    {
        $suffix = uniqid('', true);
        $brand = (new Brand())
            ->setCanonicalName($name)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '').$suffix);

        $this->entityManager->persist($brand);
        $this->entityManager->flush();

        return $brand;
    }

    private function makeProposal(
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

    private function makeProductReference(
        string $brandName,
        Category $category,
        string $nameFr,
        ProductReferenceStatus $status = ProductReferenceStatus::Approved,
    ): ProductReference {
        $suffix = uniqid('', true);
        $brand = (new Brand())
            ->setCanonicalName($brandName)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $brandName) ?? '').$suffix);
        $ref = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setUnit(ProductUnit::Piece)
            ->setStatus($status);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($ref);
        $this->entityManager->flush();

        return $ref;
    }
}
