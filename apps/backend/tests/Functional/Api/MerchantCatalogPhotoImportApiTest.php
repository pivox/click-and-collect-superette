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

        self::assertSame(200, $response->getStatusCode());
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
