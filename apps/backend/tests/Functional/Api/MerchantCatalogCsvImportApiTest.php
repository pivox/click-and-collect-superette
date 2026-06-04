<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantLocalProduct;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Repository\MerchantProductRepository;

final class MerchantCatalogCsvImportApiTest extends FunctionalApiTestCase
{
    public function testOwnerMerchantCanImportCsvAndGetsCreatedUpdatedAndErrors(): void
    {
        $merchant = $this->createUser('merchant-csv-import@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $referenceByBarcode = $this->createProductReference('Vitalait', 'Lait & produits laitiers', 'Lait demi-écrémé', '1.000', ProductUnit::Litre, '6191234567890');
        $referenceAlreadyInCatalog = $this->createProductReference('Randa', 'Pâtes', 'Spaghetti', '500.000', ProductUnit::Gramme, '6192222222222');
        $existingMerchantProduct = $this->createMerchantProduct($shop, $referenceAlreadyInCatalog, '2.100');

        $csv = "name_fr,brand,volume,unit,price_tnd,is_available,is_visible,barcode,category,merchant_note\n"
            ."Lait demi-écrémé,Vitalait,1,litre,1.650,true,true,6191234567890,Lait & produits laitiers,\n"
            ."Spaghetti,Randa,500,gramme,2.300,false,true,6192222222222,Pâtes,Rupture temporaire\n"
            ."Harissa maison,Jouda,350,gramme,4.500,true,false,6193333333333,Epicerie,Produit local\n"
            .",Boga,1,litre,2.000,true,true,6194444444444,Boissons,\n";

        $response = $this->requestRaw(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog/import-csv', $shop->getId()),
            $csv,
            'text/csv; charset=UTF-8',
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['id']);
        self::assertSame(2, $payload['created']);
        self::assertSame(1, $payload['updated']);
        self::assertSame(0, $payload['ignored']);
        self::assertCount(1, $payload['errors']);
        self::assertSame(5, $payload['errors'][0]['line']);
        self::assertSame('NAME_FR_REQUIRED', $payload['errors'][0]['code']);
        self::assertCount(3, $payload['items']);

        $merchantProductRepository = $this->entityManager->getRepository(MerchantProduct::class);
        self::assertInstanceOf(MerchantProductRepository::class, $merchantProductRepository);
        $createdReferenceOffer = $merchantProductRepository->findOneForShopAndProductReference($shop, $referenceByBarcode);
        self::assertInstanceOf(MerchantProduct::class, $createdReferenceOffer);
        self::assertSame('1.650', $createdReferenceOffer->getPriceTnd());
        self::assertTrue($createdReferenceOffer->isAvailable());
        self::assertTrue($createdReferenceOffer->isVisible());

        $this->entityManager->refresh($existingMerchantProduct);
        self::assertSame('2.300', $existingMerchantProduct->getPriceTnd());
        self::assertFalse($existingMerchantProduct->isAvailable());
        self::assertTrue($existingMerchantProduct->isVisible());
        self::assertSame('Rupture temporaire', $existingMerchantProduct->getMerchantNote());

        self::assertSame(1, $this->entityManager->getRepository(MerchantLocalProduct::class)->count([]));
        self::assertSame(2, $this->entityManager->getRepository(ProductReference::class)->count([]));
    }

    public function testImportCsvRequiresMerchantOwnership(): void
    {
        $owner = $this->createUser('merchant-csv-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-csv-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);

        $response = $this->requestRaw(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog/import-csv', $shop->getId()),
            "name_fr,brand,volume,unit,price_tnd,is_available,is_visible\nLait,Vitalait,1,litre,1.500,true,true\n",
            'text/csv',
            $otherMerchant,
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(MerchantProduct::class)->count([]));
    }

    private function createProductReference(
        string $brandName,
        string $categoryName,
        string $nameFr,
        string $volume,
        ProductUnit $unit,
        ?string $barcode,
    ): ProductReference {
        $suffix = (string) $this->entityManager->getRepository(ProductReference::class)->count([]);
        $brand = (new Brand())
            ->setCanonicalName($brandName)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $brandName) ?? '').'-'.$suffix);
        $category = (new Category())
            ->setNameFr($categoryName)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $categoryName) ?? '').'-'.$suffix);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setVolume($volume)
            ->setUnit($unit)
            ->setBarcode($barcode)
            ->setStatus(ProductReferenceStatus::Approved);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();

        return $productReference;
    }

    private function createMerchantProduct(Shop $shop, ProductReference $productReference, string $priceTnd): MerchantProduct
    {
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd($priceTnd);

        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }
}
