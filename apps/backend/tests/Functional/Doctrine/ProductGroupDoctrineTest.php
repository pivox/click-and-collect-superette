<?php

declare(strict_types=1);

namespace App\Tests\Functional\Doctrine;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductGroup;
use App\Entity\ProductGroupItem;
use App\Entity\ProductReference;
use App\Enum\ProductGroupItemImportance;
use App\Enum\ProductGroupStatus;
use App\Enum\ProductGroupVisibility;
use App\Enum\ProductUnit;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class ProductGroupDoctrineTest extends FunctionalApiTestCase
{
    public function testProductGroupPersistsWithSeveralSortedItems(): void
    {
        $brand = (new Brand())->setCanonicalName('Vitalait Group')->setSlug('vitalait-group');
        $category = (new Category())->setNameFr('Premières nécessités')->setSlug('premieres-necessites-group');
        $milk = $this->reference($brand, $category, 'Lait demi-écrémé');
        $water = $this->reference($brand, $category, 'Eau minérale');
        $group = (new ProductGroup())
            ->setNameFr('Premières nécessités')
            ->setNameAr('مواد أساسية')
            ->setSlug('premieres-necessites')
            ->setDescriptionFr('Base quotidienne')
            ->setDescriptionAr('مواد يومية')
            ->setMarketCountry('TN')
            ->setStatus(ProductGroupStatus::Published)
            ->setVisibility(ProductGroupVisibility::Merchant)
            ->setIcon('shopping-basket')
            ->setSortOrder(5);

        $group->addItem(
            (new ProductGroupItem())
                ->setProductReference($water)
                ->setSortOrder(20)
                ->setImportance(ProductGroupItemImportance::Recommended),
        );
        $group->addItem(
            (new ProductGroupItem())
                ->setProductReference($milk)
                ->setSortOrder(10)
                ->setImportance(ProductGroupItemImportance::Required),
        );

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($milk);
        $this->entityManager->persist($water);
        $this->entityManager->persist($group);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(ProductGroup::class)->find($group->getId());

        self::assertInstanceOf(ProductGroup::class, $found);
        self::assertSame('Premières nécessités', $found->getNameFr());
        self::assertSame(ProductGroupStatus::Published, $found->getStatus());
        self::assertSame(ProductGroupVisibility::Merchant, $found->getVisibility());
        self::assertSame(5, $found->getSortOrder());
        self::assertCount(2, $found->getItems());
        self::assertSame(
            ['Lait demi-écrémé', 'Eau minérale'],
            $found->getSortedItems()->map(
                static fn (ProductGroupItem $item): string => $item->getProductReference()->getNameFr(),
            )->toArray(),
        );
    }

    public function testSameProductReferenceCanBelongToSeveralGroups(): void
    {
        $brand = (new Brand())->setCanonicalName('Group Brand')->setSlug('group-brand');
        $category = (new Category())->setNameFr('Épicerie')->setSlug('epicerie-group');
        $sugar = $this->reference($brand, $category, 'Sucre blanc');
        $breakfast = (new ProductGroup())->setNameFr('Petit déjeuner')->setSlug('petit-dejeuner');
        $essentials = (new ProductGroup())->setNameFr('Premières nécessités')->setSlug('necessites');

        $breakfast->addItem((new ProductGroupItem())->setProductReference($sugar));
        $essentials->addItem((new ProductGroupItem())->setProductReference($sugar));

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($sugar);
        $this->entityManager->persist($breakfast);
        $this->entityManager->persist($essentials);
        $this->entityManager->flush();

        self::assertCount(1, $breakfast->getItems());
        self::assertCount(1, $essentials->getItems());
    }

    public function testSameProductReferenceCannotBeAddedTwiceToSameGroup(): void
    {
        $brand = (new Brand())->setCanonicalName('Duplicate Brand')->setSlug('duplicate-brand');
        $category = (new Category())->setNameFr('Boissons')->setSlug('boissons-group');
        $water = $this->reference($brand, $category, 'Eau 1.5L');
        $group = (new ProductGroup())->setNameFr('Boissons froides')->setSlug('boissons-froides');

        $group->addItem((new ProductGroupItem())->setProductReference($water)->setSortOrder(1));
        $group->addItem((new ProductGroupItem())->setProductReference($water)->setSortOrder(2));

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($water);
        $this->entityManager->persist($group);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    private function reference(Brand $brand, Category $category, string $name): ProductReference
    {
        return (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($name)
            ->setUnit(ProductUnit::Piece);
    }
}
