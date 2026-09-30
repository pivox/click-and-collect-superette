<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantLocalProduct;
use App\Entity\ProductImage;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductImageLicenseCode;
use App\Enum\ProductImageSource;
use App\Enum\ProductImageStatus;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Repository\ProductImageRepository;
use App\Service\ProductImage\ProductImageApplicationService;
use App\Service\ProductImage\ProductImageStoreCommand;
use Symfony\Component\Uid\Uuid;

/**
 * PRODUCT-IMAGE-003 (#583): admin promotion of a merchant local-product photo
 * to the official referential picture (logical duplication).
 */
final class AdminProductImagePromoteApiTest extends FunctionalApiTestCase
{
    private const string MISSING_UUID = '550e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        parent::setUp();

        if (!\function_exists('imagewebp') || !\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD with WebP support is required for the image pipeline.');
        }
    }

    public function testAdminPromotesMerchantPhotoAsOfficialReferenceImage(): void
    {
        $admin = $this->createUser('admin-promote@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-promote@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $localProduct = $this->createLocalProduct($shop, 'Harissa artisanale');
        $reference = $this->createProductReference('Jouda', 'Conserves', 'conserves', 'Harissa');
        $sourceImage = $this->storeMerchantPhoto($localProduct, $shop);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/promote', $sourceImage->getId()),
            ['product_reference_id' => $reference->getId()->toRfc4122()],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('verified', $payload['status']);
        self::assertSame('merchant_authorized', $payload['license_code']);
        self::assertSame('merchant_contribution', $payload['source']);
        self::assertSame($reference->getId()->toRfc4122(), $payload['product_reference_id']);
        // Provenance kept: the shop stays the named origin of the promoted image.
        self::assertSame($shop->getName(), $payload['source_name']);
        self::assertNotNull($payload['approved_at']);
        self::assertSame($admin->getEmail(), $payload['approved_by_email']);

        $this->entityManager->clear();

        // The promotion is a logical duplication: a NEW row owns the reference…
        $official = $this->productImageRepository()->findOfficialForProductReference(
            $this->requireReference($reference->getId()->toRfc4122()),
        );
        self::assertInstanceOf(ProductImage::class, $official);
        self::assertNotTrue($official->getId()->equals($sourceImage->getId()));
        self::assertSame($sourceImage->getOriginalPath(), $official->getOriginalPath());
        self::assertSame($sourceImage->getVariants(), $official->getVariants());

        // …while the original stays the shop's own candidate photo, untouched.
        $source = $this->productImageRepository()->find($sourceImage->getId());
        self::assertInstanceOf(ProductImage::class, $source);
        self::assertSame(ProductImageStatus::Candidate, $source->getStatus());
        self::assertNotNull($source->getMerchantLocalProduct());
        self::assertNull($source->getProductReference());

        // Audit trail in base.
        $auditLog = $this->entityManager->getRepository(AdminAuditLog::class)
            ->findOneBy(['action' => 'product_image.promoted_from_merchant']);
        self::assertInstanceOf(AdminAuditLog::class, $auditLog);
        $metadata = $auditLog->getMetadata();
        self::assertIsArray($metadata);
        self::assertSame($sourceImage->getId()->toRfc4122(), $metadata['source_product_image_id']);
        self::assertSame($reference->getId()->toRfc4122(), $metadata['product_reference_id']);
        self::assertSame($shop->getId()->toRfc4122(), $metadata['shop_id']);
    }

    public function testPromotionArchivesPreviousOfficialImageWithSupersession(): void
    {
        $admin = $this->createUser('admin-promote-replace@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-promote-replace@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $localProduct = $this->createLocalProduct($shop, 'Fromage local');
        $reference = $this->createProductReference('Délice', 'Fromages', 'fromages', 'Fromage frais');

        // Existing official (admin upload) picture.
        $previousOfficial = $this->imageApplicationService()->store(
            ProductImageStoreCommand::adminUpload($reference, $this->jpegBinary(500, 500)),
        );
        $previousOfficialId = $previousOfficial->getId()->toRfc4122();

        $sourceImage = $this->storeMerchantPhoto($localProduct, $shop);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/promote', $sourceImage->getId()),
            ['product_reference_id' => $reference->getId()->toRfc4122()],
            $admin,
        );
        self::assertSame(200, $response->getStatusCode());

        $this->entityManager->clear();
        $official = $this->productImageRepository()->findOfficialForProductReference(
            $this->requireReference($reference->getId()->toRfc4122()),
        );
        self::assertInstanceOf(ProductImage::class, $official);
        self::assertNotSame($previousOfficialId, $official->getId()->toRfc4122());

        $archived = $this->productImageRepository()->find(Uuid::fromString($previousOfficialId));
        self::assertInstanceOf(ProductImage::class, $archived);
        self::assertSame(ProductImageStatus::Archived, $archived->getStatus());
        self::assertNotNull($archived->getSupersededBy());
        self::assertTrue($archived->getSupersededBy()->getId()->equals($official->getId()));
    }

    public function testPromotionWithUnknownLicenseReturns409(): void
    {
        $admin = $this->createUser('admin-promote-license@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-promote-license@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $localProduct = $this->createLocalProduct($shop, 'Vrac amandes');
        $reference = $this->createProductReference('Saïda', 'Fruits secs', 'fruits-secs', 'Amandes');

        // Raw pipeline call with no license → resolved to Unknown for a
        // merchant_contribution source.
        $image = $this->imageApplicationService()->store(new ProductImageStoreCommand(
            contents: $this->jpegBinary(500, 500),
            source: ProductImageSource::MerchantContribution,
            statusOverride: ProductImageStatus::Candidate,
            merchantLocalProduct: $localProduct,
        ));
        self::assertSame(ProductImageLicenseCode::Unknown, $image->getLicenseCode());

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/promote', $image->getId()),
            ['product_reference_id' => $reference->getId()->toRfc4122()],
            $admin,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('PRODUCT_IMAGE_LICENSE_UNKNOWN', $this->decodeJson($response)['detail'] ?? null);
        self::assertNull($this->productImageRepository()->findOfficialForProductReference($reference));
    }

    public function testPromotionOfNonLocalProductImageReturns409(): void
    {
        $admin = $this->createUser('admin-promote-notlocal@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Vitalait', 'Lait', 'lait', 'Lait promu');
        $target = $this->createProductReference('Vitalait', 'Lait', 'lait-cible', 'Lait cible');

        // Official admin image — not a merchant local-product photo.
        $image = $this->imageApplicationService()->store(
            ProductImageStoreCommand::adminUpload($reference, $this->jpegBinary(500, 500)),
        );

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/promote', $image->getId()),
            ['product_reference_id' => $target->getId()->toRfc4122()],
            $admin,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('PRODUCT_IMAGE_NOT_PROMOTABLE', $this->decodeJson($response)['detail'] ?? null);
    }

    public function testPromotionOfArchivedMerchantPhotoReturns409(): void
    {
        $admin = $this->createUser('admin-promote-archived@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-promote-archived@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $localProduct = $this->createLocalProduct($shop, 'Olives cassées');
        $reference = $this->createProductReference('Jadida', 'Conserves', 'conserves-o', 'Olives');

        $image = $this->storeMerchantPhoto($localProduct, $shop);
        $image->setStatus(ProductImageStatus::Archived);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/promote', $image->getId()),
            ['product_reference_id' => $reference->getId()->toRfc4122()],
            $admin,
        );

        self::assertSame(409, $response->getStatusCode());
    }

    public function testPromotionOfUnknownImageReturns404(): void
    {
        $admin = $this->createUser('admin-promote-404@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Randa', 'Pâtes', 'pates', 'Spaghetti');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/promote', self::MISSING_UUID),
            ['product_reference_id' => $reference->getId()->toRfc4122()],
            $admin,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPromotionToUnknownReferenceReturns404(): void
    {
        $admin = $this->createUser('admin-promote-noref@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-promote-noref@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $localProduct = $this->createLocalProduct($shop, 'Mloukhia');
        $image = $this->storeMerchantPhoto($localProduct, $shop);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/promote', $image->getId()),
            ['product_reference_id' => self::MISSING_UUID],
            $admin,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPromotionWithoutReferenceIdReturns422(): void
    {
        $admin = $this->createUser('admin-promote-422@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-promote-422@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $localProduct = $this->createLocalProduct($shop, 'Bsissa');
        $image = $this->storeMerchantPhoto($localProduct, $shop);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/promote', $image->getId()),
            [],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testNonAdminCannotPromote(): void
    {
        $merchant = $this->createUser('merchant-promote-forbidden@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $localProduct = $this->createLocalProduct($shop, 'Chamia');
        $reference = $this->createProductReference('Chamia', 'Épicerie sucrée', 'epicerie-sucree', 'Chamia pistache');
        $image = $this->storeMerchantPhoto($localProduct, $shop);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/promote', $image->getId()),
            ['product_reference_id' => $reference->getId()->toRfc4122()],
            $merchant,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function storeMerchantPhoto(MerchantLocalProduct $localProduct, Shop $shop): ProductImage
    {
        return $this->imageApplicationService()->store(
            ProductImageStoreCommand::merchantPhoto(
                $localProduct,
                $this->jpegBinary(500, 500),
                altText: $localProduct->getNameFr(),
                sourceName: $shop->getName(),
                capturedAt: new \DateTimeImmutable(),
            ),
        );
    }

    private function createLocalProduct(Shop $shop, string $nameFr): MerchantLocalProduct
    {
        $localProduct = (new MerchantLocalProduct())
            ->setShop($shop)
            ->setNameFr($nameFr)
            ->setUnit(ProductUnit::Piece);

        $this->entityManager->persist($localProduct);
        $this->entityManager->flush();

        return $localProduct;
    }

    private function createProductReference(
        string $brandName,
        string $categoryName,
        string $categorySlug,
        string $nameFr,
    ): ProductReference {
        $suffix = (string) $this->entityManager->getRepository(ProductReference::class)->count([]);
        $brand = (new Brand())
            ->setCanonicalName($brandName)
            ->setSlug(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $brandName)).'-'.$suffix);
        $category = (new Category())
            ->setNameFr($categoryName)
            ->setSlug($categorySlug.'-'.$suffix);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setVolume('1.000')
            ->setUnit(ProductUnit::Litre)
            ->setStatus(ProductReferenceStatus::Approved);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();

        return $productReference;
    }

    private function requireReference(string $id): ProductReference
    {
        $reference = $this->entityManager->find(ProductReference::class, Uuid::fromString($id));
        self::assertInstanceOf(ProductReference::class, $reference);

        return $reference;
    }

    private function imageApplicationService(): ProductImageApplicationService
    {
        return self::getContainer()->get(ProductImageApplicationService::class);
    }

    private function productImageRepository(): ProductImageRepository
    {
        return self::getContainer()->get(ProductImageRepository::class);
    }

    private function jpegBinary(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 60));
        ob_start();
        imagejpeg($image);
        $data = (string) ob_get_clean();
        imagedestroy($image);

        return $data;
    }
}
