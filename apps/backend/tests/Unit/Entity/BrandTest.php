<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Brand;
use PHPUnit\Framework\TestCase;

final class BrandTest extends TestCase
{
    public function testBrandCanBeCreatedWithRequiredFields(): void
    {
        $brand = (new Brand())
            ->setCanonicalName('Vitalait')
            ->setSlug('vitalait');

        self::assertSame('Vitalait', $brand->getCanonicalName());
        self::assertSame('vitalait', $brand->getSlug());
        self::assertTrue($brand->isActive());
        self::assertNull($brand->getCountry());
        self::assertSame([], $brand->getAliases());
    }

    public function testBrandAliasesDefaultToEmptyArray(): void
    {
        $brand = new Brand();

        self::assertSame([], $brand->getAliases());
    }

    public function testBrandAliasesCanBeSet(): void
    {
        $brand = (new Brand())->setAliases(['Vita', 'Vitalait TN']);

        self::assertSame(['Vita', 'Vitalait TN'], $brand->getAliases());
    }

    public function testBrandCountryIsNullableByDefault(): void
    {
        self::assertNull((new Brand())->getCountry());
    }

    public function testBrandCountryCanBeSetToTunisia(): void
    {
        $brand = (new Brand())->setCountry('TN');

        self::assertSame('TN', $brand->getCountry());
    }

    public function testBrandCanBeDeactivated(): void
    {
        $brand = (new Brand())->setActive(false);

        self::assertFalse($brand->isActive());
    }

    public function testBrandHasUuidId(): void
    {
        $brand = new Brand();

        self::assertNotNull($brand->getId());
    }

    public function testTwoBrandsHaveDifferentIds(): void
    {
        self::assertNotSame((new Brand())->getId(), (new Brand())->getId());
    }
}
