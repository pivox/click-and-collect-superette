<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use PHPUnit\Framework\TestCase;

final class ProductReferenceTest extends TestCase
{
    private Brand $brand;
    private Category $category;

    protected function setUp(): void
    {
        $this->brand = (new Brand())
            ->setCanonicalName('Vitalait')
            ->setSlug('vitalait');

        $this->category = (new Category())
            ->setNameFr('Lait & produits laitiers')
            ->setSlug('lait-produits-laitiers');
    }

    public function testProductReferenceCanBeCreatedWithRequiredFields(): void
    {
        $ref = (new ProductReference())
            ->setBrand($this->brand)
            ->setCategory($this->category)
            ->setNameFr('Lait demi-écrémé')
            ->setUnit(ProductUnit::Litre);

        self::assertSame($this->brand, $ref->getBrand());
        self::assertSame($this->category, $ref->getCategory());
        self::assertSame('Lait demi-écrémé', $ref->getNameFr());
        self::assertSame(ProductUnit::Litre, $ref->getUnit());
    }

    public function testProductReferenceStatusDefaultsToDraft(): void
    {
        $ref = new ProductReference();

        self::assertSame(ProductReferenceStatus::Draft, $ref->getStatus());
    }

    public function testProductReferenceStatusCanBeSetToApproved(): void
    {
        $ref = (new ProductReference())->setStatus(ProductReferenceStatus::Approved);

        self::assertSame(ProductReferenceStatus::Approved, $ref->getStatus());
    }

    public function testProductReferenceUnitDefaultsToPiece(): void
    {
        self::assertSame(ProductUnit::Piece, (new ProductReference())->getUnit());
    }

    public function testProductReferenceBarcodeIsNullableByDefault(): void
    {
        self::assertNull((new ProductReference())->getBarcode());
    }

    public function testProductReferenceBarcodeCanBeSet(): void
    {
        $ref = (new ProductReference())->setBarcode('6191234567890');

        self::assertSame('6191234567890', $ref->getBarcode());
    }

    public function testProductReferenceArabicNameIsNullableByDefault(): void
    {
        self::assertNull((new ProductReference())->getNameAr());
    }

    public function testProductReferenceVariantsAreNullableByDefault(): void
    {
        $ref = new ProductReference();

        self::assertNull($ref->getVariantFr());
        self::assertNull($ref->getVariantAr());
    }

    public function testProductReferenceVolumeIsNullableByDefault(): void
    {
        self::assertNull((new ProductReference())->getVolume());
    }

    public function testProductReferenceVolumeCanBeSetAsDecimalString(): void
    {
        $ref = (new ProductReference())->setVolume('1.000');

        self::assertSame('1.000', $ref->getVolume());
    }

    public function testProductReferenceAliasesDefaultToEmptyArray(): void
    {
        self::assertSame([], (new ProductReference())->getAliases());
    }

    public function testProductReferenceCountryDefaultsToTunisia(): void
    {
        self::assertSame('TN', (new ProductReference())->getCountry());
    }

    public function testProductReferenceHasNoPriceMerchantField(): void
    {
        self::assertFalse(method_exists(ProductReference::class, 'getPriceTnd'));
        self::assertFalse(method_exists(ProductReference::class, 'setPriceTnd'));
    }

    public function testProductReferenceHasNoAvailabilityMerchantField(): void
    {
        self::assertFalse(method_exists(ProductReference::class, 'isAvailable'));
        self::assertFalse(method_exists(ProductReference::class, 'setAvailable'));
    }

    public function testProductReferenceHasNoVisibilityMerchantField(): void
    {
        self::assertFalse(method_exists(ProductReference::class, 'isVisible'));
        self::assertFalse(method_exists(ProductReference::class, 'setVisible'));
    }

    public function testProductReferenceHasUuidId(): void
    {
        self::assertNotNull((new ProductReference())->getId());
    }
}
