<?php

declare(strict_types=1);

namespace App\Tests\Functional\Doctrine;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Repository\MerchantProductRepository;
use App\Tests\Functional\Api\FunctionalApiTestCase;

final class MerchantProductDoctrineTest extends FunctionalApiTestCase
{
    private function makeBrand(string $slug): Brand
    {
        return (new Brand())->setCanonicalName($slug)->setSlug($slug);
    }

    private function makeCategory(string $slug): Category
    {
        return (new Category())->setNameFr($slug)->setSlug($slug);
    }

    private function makeProductReference(Brand $brand, Category $category, string $nameFr): ProductReference
    {
        return (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr);
    }

    public function testMerchantProductCanBePersistedAndRetrieved(): void
    {
        $brand = $this->makeBrand('vitalait-mp');
        $category = $this->makeCategory('lait-mp');
        $ref = $this->makeProductReference($brand, $category, 'Lait demi-écrémé');
        $shop = $this->createShop();

        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd('2.500')
            ->setMerchantNote('Disponible sauf le vendredi.');

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ref);
        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var MerchantProductRepository $repo */
        $repo = $this->entityManager->getRepository(MerchantProduct::class);
        $found = $repo->find($product->getId());

        self::assertInstanceOf(MerchantProduct::class, $found);
        self::assertEqualsWithDelta(2.5, (float) $found->getPriceTnd(), 0.0001);
        self::assertTrue($found->isAvailable());
        self::assertTrue($found->isVisible());
        self::assertSame('Disponible sauf le vendredi.', $found->getMerchantNote());
        self::assertSame('Lait demi-écrémé', $found->getProductReference()->getNameFr());
    }

    public function testTwoShopsCanSellSameProductReferenceWithDifferentPrices(): void
    {
        $brand = $this->makeBrand('vitalait-two');
        $category = $this->makeCategory('lait-two');
        $ref = $this->makeProductReference($brand, $category, 'Lait entier');

        $shop1 = $this->createShop();
        $shop2 = $this->createShop();

        $offer1 = (new MerchantProduct())
            ->setShop($shop1)
            ->setProductReference($ref)
            ->setPriceTnd('2.200');

        $offer2 = (new MerchantProduct())
            ->setShop($shop2)
            ->setProductReference($ref)
            ->setPriceTnd('2.450');

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ref);
        $this->entityManager->persist($offer1);
        $this->entityManager->persist($offer2);
        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var MerchantProductRepository $repo */
        $repo = $this->entityManager->getRepository(MerchantProduct::class);

        $found1 = $repo->find($offer1->getId());
        $found2 = $repo->find($offer2->getId());

        self::assertInstanceOf(MerchantProduct::class, $found1);
        self::assertInstanceOf(MerchantProduct::class, $found2);
        self::assertEqualsWithDelta(2.2, (float) $found1->getPriceTnd(), 0.0001);
        self::assertEqualsWithDelta(2.45, (float) $found2->getPriceTnd(), 0.0001);

        // Both reference the same ProductReference.
        self::assertSame($found1->getProductReference()->getId()->toRfc4122(), $found2->getProductReference()->getId()->toRfc4122());
    }

    public function testSameShopCannotAddSameProductReferenceTwice(): void
    {
        $brand = $this->makeBrand('vitalait-dup');
        $category = $this->makeCategory('lait-dup');
        $ref = $this->makeProductReference($brand, $category, 'Lait concentré');
        $shop = $this->createShop();

        $offer1 = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd('3.000');

        $offer2 = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd('3.500');

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ref);
        $this->entityManager->persist($offer1);
        $this->entityManager->flush();

        $this->entityManager->persist($offer2);

        $this->expectException(\Exception::class);
        $this->entityManager->flush();
    }

    public function testMerchantProductNullableFieldsDefaultCorrectly(): void
    {
        $brand = $this->makeBrand('vitalait-null');
        $category = $this->makeCategory('lait-null');
        $ref = $this->makeProductReference($brand, $category, 'Beurre doux');
        $shop = $this->createShop();

        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd('5.750');

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ref);
        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(MerchantProduct::class)->find($product->getId());

        self::assertInstanceOf(MerchantProduct::class, $found);
        self::assertNull($found->getMerchantNote());
        self::assertTrue($found->isAvailable());
        self::assertTrue($found->isVisible());
    }
}
