<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantLocalProduct;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use PHPUnit\Framework\TestCase;

final class MerchantProductTest extends TestCase
{
    private Shop $shop;
    private ProductReference $productReference;

    protected function setUp(): void
    {
        $this->shop = (new Shop())
            ->setName('Supérette Mnihla')
            ->setSlug('superette-mnihla')
            ->setQrCodeToken('qr-mnihla-001');

        $brand = (new Brand())
            ->setCanonicalName('Vitalait')
            ->setSlug('vitalait');

        $category = (new Category())
            ->setNameFr('Lait & produits laitiers')
            ->setSlug('lait-produits-laitiers');

        $this->productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Lait demi-écrémé');
    }

    public function testMerchantProductCanBeCreatedWithRequiredFields(): void
    {
        $product = (new MerchantProduct())
            ->setShop($this->shop)
            ->setProductReference($this->productReference)
            ->setPriceTnd('2.500');

        self::assertSame($this->shop, $product->getShop());
        self::assertSame($this->productReference, $product->getProductReference());
        self::assertSame('2.500', $product->getPriceTnd());
    }

    public function testIsAvailableDefaultsToTrue(): void
    {
        self::assertTrue((new MerchantProduct())->isAvailable());
    }

    public function testIsVisibleDefaultsToTrue(): void
    {
        self::assertTrue((new MerchantProduct())->isVisible());
    }

    public function testMerchantNoteIsNullableByDefault(): void
    {
        self::assertNull((new MerchantProduct())->getMerchantNote());
    }

    public function testMerchantNoteCanBeSet(): void
    {
        $product = (new MerchantProduct())->setMerchantNote('Produit en rupture le vendredi.');

        self::assertSame('Produit en rupture le vendredi.', $product->getMerchantNote());
    }

    public function testIsAvailableCanBeSetToFalse(): void
    {
        $product = (new MerchantProduct())->setAvailable(false);

        self::assertFalse($product->isAvailable());
    }

    public function testIsVisibleCanBeSetToFalse(): void
    {
        $product = (new MerchantProduct())->setVisible(false);

        self::assertFalse($product->isVisible());
    }

    public function testMerchantProductHasUuidId(): void
    {
        self::assertNotNull((new MerchantProduct())->getId());
    }

    public function testMerchantProductHasCreatedAtAndUpdatedAt(): void
    {
        $product = new MerchantProduct();

        self::assertInstanceOf(\DateTimeImmutable::class, $product->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $product->getUpdatedAt());
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

    public function testLocalProductMustBelongToMerchantProductShop(): void
    {
        $localProduct = (new MerchantLocalProduct())
            ->setShop(new Shop())
            ->setNameFr('Produit local');

        $product = (new MerchantProduct())
            ->setShop($this->shop);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Merchant local product must belong to the same shop.');

        $product->setLocalProduct($localProduct);
    }

    public function testShopCannotChangeToAnotherShopWhenLocalProductIsAlreadySet(): void
    {
        $localProduct = (new MerchantLocalProduct())
            ->setShop($this->shop)
            ->setNameFr('Produit local');

        $product = (new MerchantProduct())
            ->setLocalProduct($localProduct);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Merchant local product must belong to the same shop.');

        $product->setShop(new Shop());
    }
}
