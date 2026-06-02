<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductAiEnrichmentJob;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;

final class AdminProductAiEnrichmentApiTest extends FunctionalApiTestCase
{
    public function testAdminRunsProductAiEnrichmentWithRequestedLimit(): void
    {
        $_ENV['OPENAI_API_KEY'] = '';
        $_SERVER['OPENAI_API_KEY'] = '';

        $admin = $this->createUser('admin-ai-run@example.test', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Marque non vérifiée', 'marque-non-verifiee');
        $category = $this->createCategory('Boissons', 'boissons');
        $this->createProductReference($brand, $category, 'Eau minérale 1.5L');
        $this->createProductReference($brand, $category, 'Jus orange 1L');
        $this->createProductReference($brand, $category, 'Café moulu 250g');

        $response = $this->requestJson('POST', '/api/admin/product-ai-enrichment/run', [
            'limit' => 2,
        ], $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(2, $payload['jobs_created']);
        self::assertSame(0, $payload['jobs_submitted']);
        self::assertTrue($payload['openai_skipped']);
        self::assertSame(2, $this->entityManager->getRepository(ProductAiEnrichmentJob::class)->count([]));
    }

    public function testAdminProductAiEnrichmentRejectsInvalidLimit(): void
    {
        $admin = $this->createUser('admin-ai-invalid-limit@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/product-ai-enrichment/run', [
            'limit' => 0,
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testMerchantCannotRunProductAiEnrichment(): void
    {
        $merchant = $this->createUser('merchant-ai-run@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('POST', '/api/admin/product-ai-enrichment/run', [
            'limit' => 10,
        ], $merchant);

        self::assertSame(403, $response->getStatusCode());
    }

    private function createBrand(string $canonicalName, string $slug): Brand
    {
        $brand = (new Brand())
            ->setCanonicalName($canonicalName)
            ->setSlug($slug)
            ->setActive(true);

        $this->entityManager->persist($brand);
        $this->entityManager->flush();

        return $brand;
    }

    private function createCategory(string $nameFr, string $slug): Category
    {
        $category = (new Category())
            ->setNameFr($nameFr)
            ->setSlug($slug)
            ->setActive(true);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function createProductReference(Brand $brand, Category $category, string $nameFr): ProductReference
    {
        $ref = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setUnit(ProductUnit::Piece)
            ->setStatus(ProductReferenceStatus::Approved);

        $this->entityManager->persist($ref);
        $this->entityManager->flush();

        return $ref;
    }
}
