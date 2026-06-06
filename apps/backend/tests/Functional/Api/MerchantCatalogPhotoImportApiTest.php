<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantLocalProduct;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class MerchantCatalogPhotoImportApiTest extends FunctionalApiTestCase
{
    public function testOwnerMerchantCanPreviewPhotoImportWithReferenceMatchesAndLocalCandidates(): void
    {
        $merchant = $this->createUser('merchant-photo-import@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $reference = $this->createProductReference(
            brandName: 'Vitalait',
            categoryName: 'Lait & produits laitiers',
            nameFr: 'Lait demi-écrémé',
            volume: '1.000',
            unit: ProductUnit::Litre,
            barcode: '6191234567890',
        );

        $response = $this->requestPhotoImportPreview($shop, $merchant);

        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['id']);
        self::assertSame('receipt', $payload['source_type']);
        self::assertSame(2, $payload['detected_count']);
        self::assertSame(1, $payload['matched_reference_count']);
        self::assertSame(1, $payload['local_candidate_count']);
        self::assertCount(2, $payload['items']);

        self::assertSame('matched_reference', $payload['items'][0]['status']);
        self::assertSame($reference->getId()->toRfc4122(), $payload['items'][0]['product_reference_id']);
        self::assertSame('Lait demi-écrémé', $payload['items'][0]['name_fr']);
        self::assertSame('Vitalait', $payload['items'][0]['brand']);
        self::assertSame('6191234567890', $payload['items'][0]['barcode']);
        self::assertSame('1.650', $payload['items'][0]['suggested_price_tnd']);
        self::assertSame('0.940', $payload['items'][0]['confidence']);

        self::assertSame('local_candidate', $payload['items'][1]['status']);
        self::assertNull($payload['items'][1]['product_reference_id']);
        self::assertSame('Harissa maison', $payload['items'][1]['name_fr']);
        self::assertSame('Jouda', $payload['items'][1]['brand']);
        self::assertSame('4.500', $payload['items'][1]['suggested_price_tnd']);
    }

    public function testPhotoImportPreviewRequiresMerchantOwnership(): void
    {
        $owner = $this->createUser('merchant-photo-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-photo-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);

        $response = $this->requestPhotoImportPreview($shop, $otherMerchant);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testPhotoImportPreviewDetectsExistingLocalProducts(): void
    {
        $merchant = $this->createUser('merchant-photo-local-existing@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $this->createProductReference(
            brandName: 'Vitalait',
            categoryName: 'Lait & produits laitiers',
            nameFr: 'Lait demi-écrémé',
            volume: '1.000',
            unit: ProductUnit::Litre,
            barcode: '6191234567890',
        );
        $this->createLocalMerchantProduct(
            shop: $shop,
            nameFr: 'Harissa maison',
            brandName: 'Jouda',
            volume: '350.000',
            unit: ProductUnit::Gramme,
            barcode: null,
        );

        $response = $this->requestPhotoImportPreview($shop, $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(0, $payload['local_candidate_count']);
        self::assertSame('already_in_catalog', $payload['items'][1]['status']);
        self::assertTrue($payload['items'][1]['already_in_catalog']);
    }

    public function testOwnerMerchantCanCommitPhotoImportPreviewIntoCatalog(): void
    {
        $merchant = $this->createUser('merchant-photo-commit@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $reference = $this->createProductReference(
            brandName: 'Vitalait',
            categoryName: 'Lait & produits laitiers',
            nameFr: 'Lait demi-écrémé',
            volume: '1.000',
            unit: ProductUnit::Litre,
            barcode: '6191234567890',
        );

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog/photo-import/commit', $shop->getId()),
            [
                'items' => [
                    [
                        'line' => 1,
                        'selected' => true,
                        'product_reference_id' => $reference->getId()->toRfc4122(),
                        'name_fr' => 'Lait demi-écrémé',
                        'brand' => 'Vitalait',
                        'volume' => '1.000',
                        'unit' => 'litre',
                        'barcode' => '6191234567890',
                        'price_tnd' => '1.650',
                        'is_available' => true,
                        'is_visible' => true,
                        'merchant_note' => 'Photo ticket',
                    ],
                    [
                        'line' => 2,
                        'selected' => true,
                        'product_reference_id' => null,
                        'name_fr' => 'Harissa maison',
                        'brand' => 'Jouda',
                        'volume' => '350.000',
                        'unit' => 'gramme',
                        'barcode' => null,
                        'price_tnd' => '4.500',
                        'is_available' => true,
                        'is_visible' => false,
                        'category' => 'Epicerie',
                    ],
                    [
                        'line' => 3,
                        'selected' => false,
                        'product_reference_id' => null,
                        'name_fr' => 'Produit ignoré',
                    ],
                ],
            ],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['id']);
        self::assertSame(2, $payload['created']);
        self::assertSame(0, $payload['updated']);
        self::assertSame(1, $payload['ignored']);
        self::assertCount(2, $payload['items']);
        self::assertSame('created', $payload['items'][0]['status']);
        self::assertSame($reference->getId()->toRfc4122(), $payload['items'][0]['product_reference_id']);
        self::assertSame('created', $payload['items'][1]['status']);
        self::assertNull($payload['items'][1]['product_reference_id']);
        self::assertSame('Harissa maison', $payload['items'][1]['name_fr']);

        $merchantProducts = $this->entityManager->getRepository(MerchantProduct::class)->findCatalogForShop($shop);
        self::assertCount(2, $merchantProducts);
        self::assertSame(['Harissa maison', 'Lait demi-écrémé'], array_map(
            static fn (MerchantProduct $product): string => $product->getDisplayNameFr(),
            $merchantProducts,
        ));
    }

    public function testPhotoImportCommitRequiresMerchantOwnership(): void
    {
        $owner = $this->createUser('merchant-photo-commit-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-photo-commit-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog/photo-import/commit', $shop->getId()),
            ['items' => [[
                'line' => 1,
                'selected' => true,
                'name_fr' => 'Harissa maison',
                'brand' => 'Jouda',
                'volume' => '350.000',
                'unit' => 'gramme',
                'price_tnd' => '4.500',
                'is_available' => true,
                'is_visible' => true,
            ]]],
            $otherMerchant,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testPhotoImportCommitDeduplicatesReferenceRowsWithinBatch(): void
    {
        $merchant = $this->createUser('merchant-photo-commit-duplicate-ref@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $reference = $this->createProductReference(
            brandName: 'Vitalait',
            categoryName: 'Lait & produits laitiers',
            nameFr: 'Lait demi-écrémé',
            volume: '1.000',
            unit: ProductUnit::Litre,
            barcode: '6191234567890',
        );

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog/photo-import/commit', $shop->getId()),
            ['items' => [
                [
                    'line' => 1,
                    'selected' => true,
                    'product_reference_id' => $reference->getId()->toRfc4122(),
                    'name_fr' => 'Lait demi-écrémé',
                    'price_tnd' => '1.650',
                    'is_available' => true,
                    'is_visible' => true,
                ],
                [
                    'line' => 2,
                    'selected' => true,
                    'product_reference_id' => $reference->getId()->toRfc4122(),
                    'name_fr' => 'Lait demi-écrémé',
                    'price_tnd' => '1.700',
                    'is_available' => false,
                    'is_visible' => true,
                ],
            ]],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['created']);
        self::assertSame(1, $payload['updated']);
        self::assertSame('created', $payload['items'][0]['status']);
        self::assertSame('updated', $payload['items'][1]['status']);

        $merchantProducts = $this->entityManager->getRepository(MerchantProduct::class)->findCatalogForShop($shop);
        self::assertCount(1, $merchantProducts);
        self::assertSame('1.700', $merchantProducts[0]->getPriceTnd());
        self::assertFalse($merchantProducts[0]->isAvailable());
    }

    public function testPhotoImportCommitDeduplicatesLocalRowsWithinBatch(): void
    {
        $merchant = $this->createUser('merchant-photo-commit-duplicate-local@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog/photo-import/commit', $shop->getId()),
            ['items' => [
                [
                    'line' => 1,
                    'selected' => true,
                    'name_fr' => 'Harissa maison',
                    'brand' => 'Jouda',
                    'volume' => '350.000',
                    'unit' => 'gramme',
                    'barcode' => '6191111111111',
                    'price_tnd' => '4.500',
                    'is_available' => true,
                    'is_visible' => true,
                ],
                [
                    'line' => 2,
                    'selected' => true,
                    'name_fr' => 'Harissa maison',
                    'brand' => 'Jouda',
                    'volume' => '350.000',
                    'unit' => 'gramme',
                    'barcode' => '6191111111111',
                    'price_tnd' => '4.750',
                    'is_available' => true,
                    'is_visible' => false,
                ],
            ]],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['created']);
        self::assertSame(1, $payload['updated']);
        self::assertSame('created', $payload['items'][0]['status']);
        self::assertSame('updated', $payload['items'][1]['status']);

        $merchantProducts = $this->entityManager->getRepository(MerchantProduct::class)->findCatalogForShop($shop);
        self::assertCount(1, $merchantProducts);
        self::assertSame('4.750', $merchantProducts[0]->getPriceTnd());
        self::assertFalse($merchantProducts[0]->isVisible());
    }

    public function testPhotoImportCommitIgnoresAlreadyInCatalogAndUnselectedRows(): void
    {
        $merchant = $this->createUser('merchant-photo-commit-ignore@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/catalog/photo-import/commit', $shop->getId()),
            ['items' => [
                [
                    'line' => 1,
                    'selected' => false,
                    'name_fr' => 'Harissa maison',
                    'brand' => 'Jouda',
                    'volume' => '350.000',
                    'unit' => 'gramme',
                    'price_tnd' => '4.500',
                    'is_available' => true,
                    'is_visible' => true,
                ],
                [
                    'line' => 2,
                    'selected' => true,
                    'status' => 'already_in_catalog',
                    'name_fr' => 'Café moulu',
                    'brand' => 'Bondin',
                    'volume' => '250.000',
                    'unit' => 'gramme',
                    'price_tnd' => '6.900',
                    'is_available' => true,
                    'is_visible' => true,
                ],
            ]],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(0, $payload['created']);
        self::assertSame(0, $payload['updated']);
        self::assertSame(2, $payload['ignored']);
        self::assertSame([], $payload['items']);
        self::assertCount(0, $this->entityManager->getRepository(MerchantProduct::class)->findCatalogForShop($shop));
    }

    private function requestPhotoImportPreview(Shop $shop, User $user): Response
    {
        $photoPath = tempnam(sys_get_temp_dir(), 'catalog-photo-import-');
        self::assertIsString($photoPath);
        file_put_contents($photoPath, 'fake-jpeg-bytes');

        $request = Request::create(
            \sprintf('/api/merchant/stores/%s/catalog/photo-import/preview', $shop->getId()),
            'POST',
            parameters: ['source_type' => 'receipt'],
            files: [
                'photo' => new UploadedFile($photoPath, 'ticket.jpg', 'image/jpeg', null, true),
            ],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_TEST_USER' => $user->getEmail(),
            ],
        );

        return self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
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

    private function createLocalMerchantProduct(
        Shop $shop,
        string $nameFr,
        ?string $brandName,
        string $volume,
        ProductUnit $unit,
        ?string $barcode,
    ): MerchantProduct {
        $localProduct = (new MerchantLocalProduct())
            ->setShop($shop)
            ->setNameFr($nameFr)
            ->setBrandName($brandName)
            ->setVolume($volume)
            ->setUnit($unit)
            ->setBarcode($barcode)
            ->setPackQuantity(1);
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setLocalProduct($localProduct)
            ->setPriceTnd('4.500');

        $this->entityManager->persist($localProduct);
        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }
}
