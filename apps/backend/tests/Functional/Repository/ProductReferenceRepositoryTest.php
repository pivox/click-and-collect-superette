<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Repository\ProductReferenceRepository;
use App\Tests\Functional\Api\FunctionalApiTestCase;

final class ProductReferenceRepositoryTest extends FunctionalApiTestCase
{
    public function testSearchNeverReturnsNonApprovedReferencesWhenBrandOrBarcodeMatches(): void
    {
        $brand = (new Brand())
            ->setCanonicalName('Shared Brand')
            ->setSlug('shared-brand');
        $category = (new Category())
            ->setNameFr('Lait')
            ->setSlug('lait');

        $approved = $this->createProductReference(
            brand: $brand,
            category: $category,
            nameFr: 'Lait approuvé',
            barcode: '6190000000001',
            status: ProductReferenceStatus::Approved,
        );
        $draft = $this->createProductReference(
            brand: $brand,
            category: $category,
            nameFr: 'Produit brouillon',
            barcode: '6190000000002',
            status: ProductReferenceStatus::Draft,
        );
        $rejected = $this->createProductReference(
            brand: $brand,
            category: $category,
            nameFr: 'Produit rejeté',
            barcode: '6190000000003',
            status: ProductReferenceStatus::Rejected,
        );

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($approved);
        $this->entityManager->persist($draft);
        $this->entityManager->persist($rejected);
        $this->entityManager->flush();

        /** @var ProductReferenceRepository $repository */
        $repository = $this->entityManager->getRepository(ProductReference::class);

        $brandResults = $repository->search(query: 'Shared Brand');
        $draftBarcodeResults = $repository->search(query: '6190000000002');
        $rejectedBarcodeResults = $repository->search(query: '6190000000003');

        self::assertSame([$approved->getId()->toRfc4122()], array_map(
            static fn (ProductReference $reference): string => $reference->getId()->toRfc4122(),
            $brandResults,
        ));
        self::assertSame([], $draftBarcodeResults);
        self::assertSame([], $rejectedBarcodeResults);
    }

    private function createProductReference(
        Brand $brand,
        Category $category,
        string $nameFr,
        string $barcode,
        ProductReferenceStatus $status,
    ): ProductReference {
        return (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setVolume('1.000')
            ->setUnit(ProductUnit::Litre)
            ->setBarcode($barcode)
            ->setStatus($status);
    }
}
