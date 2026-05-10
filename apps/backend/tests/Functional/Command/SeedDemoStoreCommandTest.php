<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedDemoStoreCommandTest extends FunctionalApiTestCase
{
    public function testSeedDemoStoreCreatesMerchantStoreAndDemoCatalog(): void
    {
        $this->createApprovedProductReference('6191234560002', 'Lait demi-écrémé UHT');
        $this->createApprovedProductReference('6191234560030', 'Eau minérale Safia');

        $commandTester = $this->runCommand();

        $merchant = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'merchant@test.com']);
        self::assertInstanceOf(User::class, $merchant);
        self::assertContains('ROLE_MERCHANT', $merchant->getRoles());
        self::assertTrue($merchant->isActive());

        $shop = $this->entityManager->getRepository(Shop::class)->findOneBy(['slug' => 'superette-el-amen']);
        self::assertInstanceOf(Shop::class, $shop);
        self::assertTrue($shop->isActive());
        self::assertSame('demo-superette-el-amen', $shop->getQrCodeToken());
        self::assertSame($merchant->getId()->toRfc4122(), $shop->getOwner()?->getId()->toRfc4122());

        $merchantProducts = $this->entityManager->getRepository(MerchantProduct::class)->findBy(['shop' => $shop]);
        self::assertCount(2, $merchantProducts);

        foreach ($merchantProducts as $merchantProduct) {
            self::assertTrue($merchantProduct->isVisible());
            self::assertTrue($merchantProduct->isAvailable());
            self::assertSame(ProductReferenceStatus::Approved, $merchantProduct->getProductReference()->getStatus());
        }

        self::assertStringContainsString('products_added', $commandTester->getDisplay());
        self::assertStringContainsString('/api/stores/'.$shop->getId()->toRfc4122().'/catalog', $commandTester->getDisplay());
    }

    public function testSeedDemoStoreCanRunTwiceWithoutDuplicatingCatalog(): void
    {
        $this->createApprovedProductReference('6191234560002', 'Lait demi-écrémé UHT');
        $this->createApprovedProductReference('6191234560030', 'Eau minérale Safia');

        $this->runCommand();
        $firstCount = $this->entityManager->getRepository(MerchantProduct::class)->count([]);

        $this->runCommand();
        $secondCount = $this->entityManager->getRepository(MerchantProduct::class)->count([]);

        self::assertSame(2, $firstCount);
        self::assertSame($firstCount, $secondCount);
    }

    public function testSeedDemoStoreAllCatalogAssignsEveryApprovedProductReferenceOnly(): void
    {
        $this->createApprovedProductReference('6191234560002', 'Lait demi-écrémé UHT');
        $this->createApprovedProductReference('6191234560030', 'Eau minérale Safia');
        $this->createProductReference('6191234569999', 'Produit brouillon', ProductReferenceStatus::Draft);

        $this->runCommand(['--catalog' => 'all']);

        $shop = $this->entityManager->getRepository(Shop::class)->findOneBy(['slug' => 'superette-el-amen']);
        self::assertInstanceOf(Shop::class, $shop);

        $merchantProducts = $this->entityManager->getRepository(MerchantProduct::class)->findBy(['shop' => $shop]);
        self::assertCount(2, $merchantProducts);

        foreach ($merchantProducts as $merchantProduct) {
            self::assertSame(ProductReferenceStatus::Approved, $merchantProduct->getProductReference()->getStatus());
        }
    }

    public function testSeedDemoStoreCatalogIsReadableThroughPublicCatalogApi(): void
    {
        $this->createApprovedProductReference('6191234560002', 'Lait demi-écrémé UHT');

        $this->runCommand();

        $shop = $this->entityManager->getRepository(Shop::class)->findOneBy(['slug' => 'superette-el-amen']);
        self::assertInstanceOf(Shop::class, $shop);

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shop->getId()));
        $payload = $this->decodeJson($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $payload['items']);
        self::assertSame('Lait demi-écrémé UHT', $payload['items'][0]['name_fr']);
        self::assertSame('1.650', $payload['items'][0]['price_tnd']);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(array $input = []): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:dev:seed-demo-store');
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute($input);

        self::assertSame(0, $exitCode, $commandTester->getDisplay());

        return $commandTester;
    }

    private function createApprovedProductReference(string $barcode, string $nameFr): ProductReference
    {
        return $this->createProductReference($barcode, $nameFr, ProductReferenceStatus::Approved);
    }

    private function createProductReference(string $barcode, string $nameFr, ProductReferenceStatus $status): ProductReference
    {
        $suffix = (string) $this->entityManager->getRepository(ProductReference::class)->count([]);
        $brand = (new Brand())
            ->setCanonicalName('Demo Brand '.$suffix)
            ->setSlug('demo-brand-'.$suffix);
        $category = (new Category())
            ->setNameFr('Demo Category '.$suffix)
            ->setSlug('demo-category-'.$suffix);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setVolume('1.000')
            ->setUnit(ProductUnit::Litre)
            ->setBarcode($barcode)
            ->setStatus($status);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();

        return $productReference;
    }
}
