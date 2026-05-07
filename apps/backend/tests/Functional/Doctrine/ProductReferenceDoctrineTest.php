<?php

declare(strict_types=1);

namespace App\Tests\Functional\Doctrine;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductReferenceRepository;
use App\Tests\Functional\Api\FunctionalApiTestCase;

final class ProductReferenceDoctrineTest extends FunctionalApiTestCase
{
    public function testBrandCanBePersistedAndRetrieved(): void
    {
        $brand = (new Brand())
            ->setCanonicalName('Vitalait')
            ->setSlug('vitalait')
            ->setCountry('TN')
            ->setAliases(['Vita']);

        $this->entityManager->persist($brand);
        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var BrandRepository $repo */
        $repo = $this->entityManager->getRepository(Brand::class);
        $found = $repo->find($brand->getId());

        self::assertInstanceOf(Brand::class, $found);
        self::assertSame('Vitalait', $found->getCanonicalName());
        self::assertSame('vitalait', $found->getSlug());
        self::assertSame('TN', $found->getCountry());
        self::assertSame(['Vita'], $found->getAliases());
        self::assertTrue($found->isActive());
    }

    public function testCategoryCanBePersistedWithOptionalArabicNameAndParent(): void
    {
        $parent = (new Category())
            ->setNameFr('Épicerie')
            ->setSlug('epicerie');

        $child = (new Category())
            ->setNameFr('Lait & produits laitiers')
            ->setNameAr('الحليب ومنتجات الألبان')
            ->setSlug('lait-produits-laitiers')
            ->setParent($parent)
            ->setSortOrder(1);

        $this->entityManager->persist($parent);
        $this->entityManager->persist($child);
        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var CategoryRepository $repo */
        $repo = $this->entityManager->getRepository(Category::class);
        $found = $repo->find($child->getId());

        self::assertInstanceOf(Category::class, $found);
        self::assertSame('Lait & produits laitiers', $found->getNameFr());
        self::assertSame('الحليب ومنتجات الألبان', $found->getNameAr());
        self::assertSame(1, $found->getSortOrder());
        self::assertNotNull($found->getParent());
        self::assertSame('Épicerie', $found->getParent()->getNameFr());
    }

    public function testProductReferenceCanBePersistedAndRetrieved(): void
    {
        $brand = (new Brand())->setCanonicalName('Vitalait')->setSlug('vitalait-pr');
        $category = (new Category())->setNameFr('Lait')->setSlug('lait-pr');

        $ref = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Lait demi-écrémé')
            ->setNameAr('حليب نصف منزوع الدسم')
            ->setVariantFr('Demi-écrémé')
            ->setUnit(ProductUnit::Litre)
            ->setVolume('1.000')
            ->setBarcode('6191234567890')
            ->setStatus(ProductReferenceStatus::Approved);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ref);
        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var ProductReferenceRepository $repo */
        $repo = $this->entityManager->getRepository(ProductReference::class);
        $found = $repo->find($ref->getId());

        self::assertInstanceOf(ProductReference::class, $found);
        self::assertSame('Lait demi-écrémé', $found->getNameFr());
        self::assertSame('حليب نصف منزوع الدسم', $found->getNameAr());
        self::assertSame('Demi-écrémé', $found->getVariantFr());
        self::assertSame(ProductUnit::Litre, $found->getUnit());
        // SQLite normalizes DECIMAL to its minimal representation; compare as float.
        self::assertEqualsWithDelta(1.0, (float) $found->getVolume(), 0.0001);
        self::assertSame('6191234567890', $found->getBarcode());
        self::assertSame(ProductReferenceStatus::Approved, $found->getStatus());
        self::assertSame('Vitalait', $found->getBrand()->getCanonicalName());
        self::assertSame('Lait', $found->getCategory()->getNameFr());
    }

    public function testProductReferenceWithNullableBarcodeAndVolume(): void
    {
        $brand = (new Brand())->setCanonicalName('Sans marque')->setSlug('sans-marque');
        $category = (new Category())->setNameFr('Divers')->setSlug('divers');

        $ref = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit sans barcode')
            ->setUnit(ProductUnit::Piece);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ref);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(ProductReference::class)->find($ref->getId());

        self::assertInstanceOf(ProductReference::class, $found);
        self::assertNull($found->getBarcode());
        self::assertNull($found->getVolume());
        self::assertSame(ProductReferenceStatus::Draft, $found->getStatus());
    }

    public function testSameProductReferenceCannotBeUpsertedWithDuplicateBarcode(): void
    {
        $brand = (new Brand())->setCanonicalName('TestBrand')->setSlug('test-brand-dup');
        $category = (new Category())->setNameFr('TestCat')->setSlug('test-cat-dup');

        $ref1 = (new ProductReference())
            ->setBrand($brand)->setCategory($category)
            ->setNameFr('Produit A')->setUnit(ProductUnit::Piece)
            ->setBarcode('0000000000001');

        $ref2 = (new ProductReference())
            ->setBrand($brand)->setCategory($category)
            ->setNameFr('Produit B')->setUnit(ProductUnit::Piece)
            ->setBarcode('0000000000001');

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ref1);
        $this->entityManager->flush();

        $this->entityManager->persist($ref2);

        $this->expectException(\Exception::class);
        $this->entityManager->flush();
    }
}
