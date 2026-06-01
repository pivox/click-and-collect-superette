<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\ProductAiEnrichmentJob;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Service\ProductAiEnrichmentResult;
use App\Service\ProductAiEnrichmentResultApplier;
use App\Tests\Functional\Api\FunctionalApiTestCase;

final class ProductAiEnrichmentResultApplierTest extends FunctionalApiTestCase
{
    public function testAppliesValidatedOpenAiResultDirectlyAndStoresAuditTrail(): void
    {
        $shop = $this->createShop();
        $genericBrand = (new Brand())
            ->setCanonicalName('Marque non vérifiée')
            ->setSlug('marque-non-verifiee');
        $category = (new Category())
            ->setNameFr('Boissons')
            ->setSlug('boissons');
        $productReference = (new ProductReference())
            ->setBrand($genericBrand)
            ->setCategory($category)
            ->setNameFr('Eau minerale 1.5 l')
            ->setVolume('1.500')
            ->setUnit(ProductUnit::Litre)
            ->setStatus(ProductReferenceStatus::Approved);
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('0.500')
            ->setAvailable(true)
            ->setVisible(true);
        $job = new ProductAiEnrichmentJob($productReference);

        $this->entityManager->persist($genericBrand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->persist($merchantProduct);
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $applier = new ProductAiEnrichmentResultApplier($this->entityManager);
        $result = new ProductAiEnrichmentResult(
            brand: 'Safia',
            barcode: '6191234567890',
            estimatedPriceTnd: '0.900',
            vatCode: 'TVA_19',
            nameAr: 'ماء معدني 1.5 لتر',
            nameTnLatin: 'mé safia 1.5L',
            confidence: '0.860',
            warnings: ['price_estimated'],
        );

        $applier->apply($job, $result);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $updated = $this->entityManager->getRepository(ProductReference::class)->find($productReference->getId());
        self::assertInstanceOf(ProductReference::class, $updated);
        self::assertSame('Safia', $updated->getBrand()->getCanonicalName());
        self::assertSame('6191234567890', $updated->getBarcode());
        self::assertSame('ماء معدني 1.5 لتر', $updated->getNameAr());
        self::assertContains('mé safia 1.5L', $updated->getAliases());
        self::assertSame('0.860', $updated->getAiConfidence());
        self::assertSame('openai', $updated->getAiSource());
        self::assertNotNull($updated->getAiEnrichedAt());
        self::assertSame('Marque non vérifiée', $updated->getAiPreviousValues()['brand'] ?? null);

        $updatedMerchantProduct = $this->entityManager->getRepository(MerchantProduct::class)->find($merchantProduct->getId());
        self::assertInstanceOf(MerchantProduct::class, $updatedMerchantProduct);
        self::assertSame('0.900', $updatedMerchantProduct->getPriceTnd());

        $updatedJob = $this->entityManager->getRepository(ProductAiEnrichmentJob::class)->find($job->getId());
        self::assertInstanceOf(ProductAiEnrichmentJob::class, $updatedJob);
        self::assertTrue($updatedJob->isApplied());
    }

    public function testRejectsInvalidFactLikeBarcodeWithoutChangingProduct(): void
    {
        $brand = (new Brand())
            ->setCanonicalName('Marque non vérifiée')
            ->setSlug('marque-non-verifiee');
        $category = (new Category())->setNameFr('Divers')->setSlug('divers');
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit test')
            ->setStatus(ProductReferenceStatus::Approved);
        $job = new ProductAiEnrichmentJob($productReference);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $applier = new ProductAiEnrichmentResultApplier($this->entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AI_RESULT_BARCODE_INVALID');

        $applier->apply($job, new ProductAiEnrichmentResult(
            brand: 'Safia',
            barcode: 'not-an-ean',
            estimatedPriceTnd: '0.900',
            vatCode: 'TVA_19',
            nameAr: 'ماء',
            nameTnLatin: 'me',
            confidence: '0.800',
            warnings: [],
        ));
    }
}
