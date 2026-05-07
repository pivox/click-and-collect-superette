<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testCategoryCanBeCreatedWithRequiredFields(): void
    {
        $category = (new Category())
            ->setNameFr('Lait & produits laitiers')
            ->setSlug('lait-produits-laitiers');

        self::assertSame('Lait & produits laitiers', $category->getNameFr());
        self::assertSame('lait-produits-laitiers', $category->getSlug());
        self::assertNull($category->getNameAr());
        self::assertNull($category->getParent());
        self::assertSame(0, $category->getSortOrder());
        self::assertTrue($category->isActive());
    }

    public function testCategoryArabicNameIsNullableByDefault(): void
    {
        self::assertNull((new Category())->getNameAr());
    }

    public function testCategoryArabicNameCanBeSet(): void
    {
        $category = (new Category())->setNameAr('الحليب ومنتجات الألبان');

        self::assertSame('الحليب ومنتجات الألبان', $category->getNameAr());
    }

    public function testCategoryParentIsNullableByDefault(): void
    {
        self::assertNull((new Category())->getParent());
    }

    public function testCategoryCanHaveAParent(): void
    {
        $parent = (new Category())
            ->setNameFr('Épicerie')
            ->setSlug('epicerie');

        $child = (new Category())
            ->setNameFr('Lait & produits laitiers')
            ->setSlug('lait-produits-laitiers')
            ->setParent($parent);

        self::assertSame($parent, $child->getParent());
    }

    public function testCategorySortOrderDefaultsToZero(): void
    {
        self::assertSame(0, (new Category())->getSortOrder());
    }

    public function testCategoryHasUuidId(): void
    {
        self::assertNotNull((new Category())->getId());
    }
}
