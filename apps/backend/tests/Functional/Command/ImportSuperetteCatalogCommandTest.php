<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Kadhia;
use App\Entity\KadhiaLine;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportSuperetteCatalogCommandTest extends FunctionalApiTestCase
{
    public function testImportSuperetteCatalogResetsOldCatalogAndSyncsActiveShops(): void
    {
        $activeShop = $this->createShop();
        $inactiveShop = $this->createShop(active: false);
        $customer = $this->createUser('client.catalog-reset@example.test', ['ROLE_CUSTOMER']);
        $this->createOldCatalogWithKadhiaAndOrder($activeShop, $customer);

        $catalogPath = $this->writeCatalogFixture([
            [
                'sku' => 'SUPTUN-0001',
                'name_fr' => 'Couscous fin 250 g',
                'name_ar' => 'كسكسي رقيق 250 غ',
                'category' => 'Epicerie salee',
                'subcategory' => 'Cereales et feculents',
                'unit' => '250 g',
                'brand' => null,
                'brand_candidates' => ['Randa', 'Warda'],
                'estimated_price_tnd' => '1.850',
                'status' => 'draft',
                'tags' => ['epicerie-salee', 'unite-250-g'],
            ],
            [
                'sku' => 'SUPTUN-0002',
                'name_fr' => 'Eau minerale 1.5 l',
                'name_ar' => null,
                'category' => 'Boissons',
                'subcategory' => 'Eaux',
                'unit' => '1.5 l',
                'brand' => 'Safia',
                'brand_candidates' => [],
                'estimated_price_tnd' => null,
                'status' => 'draft',
                'tags' => ['boissons', 'eaux'],
            ],
        ]);

        $commandTester = $this->runCommand([
            'catalogPath' => $catalogPath,
            '--reset' => true,
            '--sync-shop-catalogs' => true,
            '--reference-status' => 'approved',
        ]);

        self::assertSame(2, $this->entityManager->getRepository(ProductReference::class)->count([]));
        self::assertSame(2, $this->entityManager->getRepository(MerchantProduct::class)->count(['shop' => $activeShop]));
        self::assertSame(0, $this->entityManager->getRepository(MerchantProduct::class)->count(['shop' => $inactiveShop]));
        self::assertSame(0, $this->entityManager->getRepository(Kadhia::class)->count([]));
        self::assertSame(0, $this->entityManager->getRepository(Order::class)->count([]));

        $genericBrand = $this->entityManager->getRepository(Brand::class)->findOneBy(['slug' => 'marque-non-verifiee']);
        self::assertInstanceOf(Brand::class, $genericBrand);
        self::assertSame('Marque non vérifiée', $genericBrand->getCanonicalName());

        $couscous = $this->entityManager->getRepository(ProductReference::class)->findOneBy(['nameFr' => 'Couscous fin 250 g']);
        self::assertInstanceOf(ProductReference::class, $couscous);
        self::assertSame(ProductReferenceStatus::Approved, $couscous->getStatus());
        self::assertSame('250.000', $couscous->getVolume());
        self::assertSame(ProductUnit::Gramme, $couscous->getUnit());
        self::assertSame(['SUPTUN-0001', 'Randa', 'Warda', 'epicerie-salee', 'unite-250-g'], $couscous->getAliases());

        $merchantProduct = $this->entityManager->getRepository(MerchantProduct::class)->findOneBy([
            'shop' => $activeShop,
            'productReference' => $couscous,
        ]);
        self::assertInstanceOf(MerchantProduct::class, $merchantProduct);
        self::assertSame('1.850', $merchantProduct->getPriceTnd());
        self::assertTrue($merchantProduct->isAvailable());
        self::assertTrue($merchantProduct->isVisible());

        self::assertMatchesRegularExpression('/products_imported\s+2/', $commandTester->getDisplay());
        self::assertMatchesRegularExpression('/merchant_products_created\s+2/', $commandTester->getDisplay());
        self::assertMatchesRegularExpression('/kadhias_deleted\s+1/', $commandTester->getDisplay());
        self::assertMatchesRegularExpression('/orders_deleted\s+1/', $commandTester->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(array $input): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:products:import-superette-catalog');
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute($input);

        self::assertSame(0, $exitCode, $commandTester->getDisplay());

        return $commandTester;
    }

    /**
     * @param list<array<string, mixed>> $products
     */
    private function writeCatalogFixture(array $products): string
    {
        $path = sys_get_temp_dir().'/catalogue_superette_test_'.bin2hex(random_bytes(6)).'.json';
        file_put_contents($path, json_encode([
            'meta' => [
                'catalog_name' => 'catalogue_superette_tunisie',
                'catalog_version' => '1.0.0',
            ],
            'products' => $products,
        ], \JSON_THROW_ON_ERROR));

        return $path;
    }

    private function createOldCatalogWithKadhiaAndOrder(\App\Entity\Shop $shop, \App\Entity\User $customer): void
    {
        $brand = (new Brand())
            ->setCanonicalName('Ancienne marque')
            ->setSlug('ancienne-marque');
        $category = (new Category())
            ->setNameFr('Ancienne categorie')
            ->setSlug('ancienne-categorie');
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Ancien produit')
            ->setVolume('1.000')
            ->setUnit(ProductUnit::Piece)
            ->setStatus(ProductReferenceStatus::Approved);
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('1.000')
            ->setAvailable(true)
            ->setVisible(true);

        $kadhia = (new Kadhia())
            ->setCustomer($customer)
            ->setShop($shop);
        $kadhiaLine = (new KadhiaLine())
            ->setMerchantProduct($merchantProduct)
            ->setQuantity(1)
            ->setUnitPriceTnd('1.000');
        $kadhia->addLine($kadhiaLine);

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setKadhia($kadhia);
        $orderLine = (new OrderLine())
            ->setMerchantProduct($merchantProduct)
            ->setQuantity(1)
            ->setUnitPriceTnd('1.000')
            ->setLineTotalTnd('1.000');
        $order->addLine($orderLine);
        $order->recomputeTotal();

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->persist($merchantProduct);
        $this->entityManager->persist($kadhia);
        $this->entityManager->persist($order);
        $this->entityManager->flush();
    }
}
