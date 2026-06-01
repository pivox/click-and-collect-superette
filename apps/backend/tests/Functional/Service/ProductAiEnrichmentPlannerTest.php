<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductAiEnrichmentJob;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Service\ProductAiEnrichmentPlanner;
use App\Tests\Functional\Api\FunctionalApiTestCase;

final class ProductAiEnrichmentPlannerTest extends FunctionalApiTestCase
{
    public function testPlansMissingProductEnrichmentJobsWithoutDuplicates(): void
    {
        $productReference = $this->createProductReference(
            brandName: 'Marque non vérifiée',
            brandSlug: 'marque-non-verifiee',
            nameAr: null,
            barcode: null,
        );
        $this->createProductReference(
            brandName: 'Safia',
            brandSlug: 'safia',
            nameAr: 'ماء معدني',
            barcode: '6191234567890',
        );

        $planner = new ProductAiEnrichmentPlanner($this->entityManager);

        $firstResult = $planner->planMissingProductJobs(10);
        $secondResult = $planner->planMissingProductJobs(10);

        self::assertSame(1, $firstResult->createdJobs);
        self::assertSame(0, $secondResult->createdJobs);
        self::assertSame(1, $this->entityManager->getRepository(ProductAiEnrichmentJob::class)->count([]));

        $job = $this->entityManager->getRepository(ProductAiEnrichmentJob::class)->findOneBy(['productReference' => $productReference]);
        self::assertInstanceOf(ProductAiEnrichmentJob::class, $job);
        self::assertSame($productReference->getId()->toRfc4122(), $job->getProductReference()->getId()->toRfc4122());
    }

    private function createProductReference(string $brandName, string $brandSlug, ?string $nameAr, ?string $barcode): ProductReference
    {
        $brand = (new Brand())
            ->setCanonicalName($brandName)
            ->setSlug($brandSlug);
        $category = (new Category())
            ->setNameFr('Boissons')
            ->setSlug('boissons-'.$brandSlug);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Eau minerale '.$brandSlug)
            ->setNameAr($nameAr)
            ->setVolume('1.500')
            ->setUnit(ProductUnit::Litre)
            ->setBarcode($barcode)
            ->setStatus(ProductReferenceStatus::Approved);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();

        return $productReference;
    }
}
