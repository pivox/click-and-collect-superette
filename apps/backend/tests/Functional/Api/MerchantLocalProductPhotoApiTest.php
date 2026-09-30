<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\MerchantLocalProduct;
use App\Entity\MerchantProduct;
use App\Entity\ProductImage;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductImageLicenseCode;
use App\Enum\ProductImageSource;
use App\Enum\ProductImageStatus;
use App\Enum\ProductUnit;
use App\Repository\ProductImageRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Uid\Uuid;

/**
 * PRODUCT-IMAGE-003 (#583): merchant photo contribution for local products.
 */
final class MerchantLocalProductPhotoApiTest extends FunctionalApiTestCase
{
    private const string MISSING_UUID = '550e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        parent::setUp();

        if (!\function_exists('imagewebp') || !\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD with WebP support is required for the image pipeline.');
        }
    }

    public function testMerchantUploadsPhotoForOwnLocalProduct(): void
    {
        $merchant = $this->createUser('merchant-photo-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        [$localProduct] = $this->createLocalProduct($shop, 'Harissa maison');

        $fixture = $this->jpegWithExif(600, 600);
        self::assertStringContainsString("Exif\x00\x00", $fixture);

        $response = $this->uploadPhoto($shop, $localProduct, $fixture, $merchant);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayHasKey('image', $payload);
        $image = $payload['image'];
        self::assertSame('candidate', $image['status']);
        self::assertSame('merchant_authorized', $image['license_code']);
        self::assertStringContainsString('/200.webp', (string) $image['thumbnail_url']);
        self::assertStringContainsString('/400.webp', (string) $image['card_url']);
        self::assertStringContainsString('/800.webp', (string) $image['detail_url']);
        self::assertStringContainsString('/1200.webp', (string) $image['zoom_url']);
        self::assertStringContainsString('/fallback.jpg', (string) $image['fallback_jpeg_url']);

        $stored = $this->productImageRepository()->findCurrentForMerchantLocalProduct($localProduct);
        self::assertInstanceOf(ProductImage::class, $stored);
        self::assertSame(ProductImageSource::MerchantContribution, $stored->getSource());
        self::assertSame(ProductImageStatus::Candidate, $stored->getStatus());
        self::assertSame(ProductImageLicenseCode::MerchantAuthorized, $stored->getLicenseCode());
        // Acceptance criterion: the merchant origin and dates are kept.
        self::assertSame($shop->getName(), $stored->getSourceName());
        self::assertNotNull($stored->getCollectedAt());
        self::assertNotNull($stored->getCapturedAt());
        self::assertSame('Harissa maison', $stored->getAltText());
        self::assertNull($stored->getProductReference());
        self::assertNull($stored->getProductReferenceProposal());

        // EXIF/GPS strip: the stored original is a GD re-encode without metadata.
        $originalFile = $this->storedOriginalFile($stored);
        self::assertFileExists($originalFile);
        $storedBytes = (string) file_get_contents($originalFile);
        self::assertStringNotContainsString("Exif\x00\x00", $storedBytes);
    }

    public function testMerchantPhotoAccepts350PixelsAboveMerchantMinimum(): void
    {
        // 350px is below the admin referential minimum (400) but above the
        // merchant one (320) — the merchant pipeline must accept it.
        $merchant = $this->createUser('merchant-photo-350@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        [$localProduct] = $this->createLocalProduct($shop, 'Vrac pois chiches');

        $response = $this->uploadPhoto($shop, $localProduct, $this->jpegBinary(350, 350), $merchant);

        self::assertSame(201, $response->getStatusCode());
    }

    public function testTooSmallPhotoReturns422WithMerchantCode(): void
    {
        $merchant = $this->createUser('merchant-photo-small@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        [$localProduct] = $this->createLocalProduct($shop, 'Olives en vrac');

        $response = $this->uploadPhoto($shop, $localProduct, $this->jpegBinary(300, 300), $merchant);

        self::assertSame(422, $response->getStatusCode());
        // Plain-controller errors are not serialized by API Platform in the test
        // env — assert the stable error code is carried by the response body.
        self::assertStringContainsString('MERCHANT_PHOTO_TOO_SMALL', (string) $response->getContent());
    }

    public function testUnsupportedMimeReturns422(): void
    {
        $merchant = $this->createUser('merchant-photo-gif@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        [$localProduct] = $this->createLocalProduct($shop, 'Pain traditionnel');

        $response = $this->uploadPhoto($shop, $localProduct, $this->gifBinary(400, 400), $merchant);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUnreadableFileReturns422(): void
    {
        $merchant = $this->createUser('merchant-photo-bad@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        [$localProduct] = $this->createLocalProduct($shop, 'Fromage local');

        $response = $this->uploadPhoto($shop, $localProduct, 'not an image at all', $merchant);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testNonOwnerMerchantCannotUpload(): void
    {
        $owner = $this->createUser('merchant-photo-owner2@example.test', ['ROLE_MERCHANT']);
        $other = $this->createUser('merchant-photo-intruder@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);
        [$localProduct] = $this->createLocalProduct($shop, 'Miel de montagne');

        $response = $this->uploadPhoto($shop, $localProduct, $this->jpegBinary(500, 500), $other);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testCustomerCannotUpload(): void
    {
        $owner = $this->createUser('merchant-photo-owner3@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-photo@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($owner);
        [$localProduct] = $this->createLocalProduct($shop, 'Zgougou');

        $response = $this->uploadPhoto($shop, $localProduct, $this->jpegBinary(500, 500), $customer);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testLocalProductOfAnotherShopReturns404(): void
    {
        $merchant = $this->createUser('merchant-photo-cross@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-photo-cross-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $otherShop = $this->createShop($otherMerchant);
        [$foreignLocalProduct] = $this->createLocalProduct($otherShop, 'Produit voisin');

        // The merchant targets his own store with a local product of another shop.
        $response = $this->uploadPhoto($shop, $foreignLocalProduct, $this->jpegBinary(500, 500), $merchant);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUnknownLocalProductReturns404(): void
    {
        $merchant = $this->createUser('merchant-photo-404@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_TEST_USER' => $merchant->getEmail()];
        $request = Request::create(
            \sprintf('/api/merchant/stores/%s/local-products/%s/photo', $shop->getId(), self::MISSING_UUID),
            'POST',
            files: ['photo' => $this->makeUpload($this->jpegBinary(500, 500), 'a.jpg', 'image/jpeg')],
            server: $server,
        );
        $response = self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testMissingFileReturns400(): void
    {
        $merchant = $this->createUser('merchant-photo-nofile@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        [$localProduct] = $this->createLocalProduct($shop, 'Chorba maison');

        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_TEST_USER' => $merchant->getEmail()];
        $request = Request::create($this->photoPath($shop, $localProduct), 'POST', server: $server);
        $response = self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);

        self::assertSame(400, $response->getStatusCode());
    }

    public function testReplacingPhotoArchivesPreviousOneWithSupersession(): void
    {
        $merchant = $this->createUser('merchant-photo-replace@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        [$localProduct] = $this->createLocalProduct($shop, 'Couscous vrac');

        $first = $this->uploadPhoto($shop, $localProduct, $this->jpegBinary(500, 500), $merchant);
        self::assertSame(201, $first->getStatusCode());
        $firstImage = $this->productImageRepository()->findCurrentForMerchantLocalProduct($localProduct);
        self::assertInstanceOf(ProductImage::class, $firstImage);
        $firstImageId = $firstImage->getId()->toRfc4122();

        $second = $this->uploadPhoto($shop, $localProduct, $this->pngBinary(500, 500), $merchant);
        self::assertSame(201, $second->getStatusCode());

        $this->entityManager->clear();
        $localProduct = $this->entityManager->getRepository(MerchantLocalProduct::class)->find($localProduct->getId());
        self::assertInstanceOf(MerchantLocalProduct::class, $localProduct);
        $current = $this->productImageRepository()->findCurrentForMerchantLocalProduct($localProduct);
        self::assertInstanceOf(ProductImage::class, $current);
        self::assertNotSame($firstImageId, $current->getId()->toRfc4122());

        $previous = $this->productImageRepository()->find(Uuid::fromString($firstImageId));
        self::assertInstanceOf(ProductImage::class, $previous);
        self::assertSame(ProductImageStatus::Archived, $previous->getStatus());
        self::assertNotNull($previous->getSupersededBy());
        self::assertTrue($previous->getSupersededBy()->getId()->equals($current->getId()));
    }

    public function testDeleteArchivesPhotoAndCatalogsDropIt(): void
    {
        $merchant = $this->createUser('merchant-photo-delete@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        [$localProduct, $merchantProduct] = $this->createLocalProduct($shop, 'Ricotta locale');

        $upload = $this->uploadPhoto($shop, $localProduct, $this->jpegBinary(500, 500), $merchant);
        self::assertSame(201, $upload->getStatusCode());

        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_TEST_USER' => $merchant->getEmail()];
        $request = Request::create($this->photoPath($shop, $localProduct), 'DELETE', server: $server);
        $response = self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        self::assertSame(204, $response->getStatusCode());

        $this->entityManager->clear();
        $localProduct = $this->entityManager->getRepository(MerchantLocalProduct::class)->find($localProduct->getId());
        self::assertInstanceOf(MerchantLocalProduct::class, $localProduct);
        self::assertNull($this->productImageRepository()->findCurrentForMerchantLocalProduct($localProduct));

        // The archived photo leaves the public catalog (pattern #18: no image key).
        $catalog = $this->decodeJson($this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shop->getId())));
        $item = $this->findCatalogItem($catalog['items'], $merchantProduct->getId()->toRfc4122());
        self::assertArrayNotHasKey('image', $item);

        // A second DELETE finds no current photo anymore.
        $request = Request::create($this->photoPath($shop, $localProduct), 'DELETE', server: $server);
        $response = self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        self::assertSame(404, $response->getStatusCode());
    }

    public function testPublicAndMerchantCatalogsExposeMerchantPhoto(): void
    {
        $merchant = $this->createUser('merchant-photo-catalog@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        [$localProduct, $merchantProduct] = $this->createLocalProduct($shop, 'Harissa artisanale');

        $upload = $this->uploadPhoto($shop, $localProduct, $this->jpegBinary(600, 600), $merchant);
        self::assertSame(201, $upload->getStatusCode());

        // Public catalog of the shop (the photo is shop-scoped by construction).
        $publicCatalog = $this->decodeJson($this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shop->getId())));
        $publicItem = $this->findCatalogItem($publicCatalog['items'], $merchantProduct->getId()->toRfc4122());
        self::assertArrayHasKey('image', $publicItem);
        self::assertSame('candidate', $publicItem['image']['status']);
        self::assertStringContainsString('/400.webp', (string) $publicItem['image']['card_url']);

        // Merchant catalog listing.
        $merchantCatalog = $this->decodeJson(
            $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/catalog', $shop->getId()), user: $merchant),
        );
        $merchantItem = $this->findCatalogItem($merchantCatalog['items'], $merchantProduct->getId()->toRfc4122());
        self::assertArrayHasKey('image', $merchantItem);
        self::assertSame('candidate', $merchantItem['image']['status']);
        self::assertStringContainsString('/200.webp', (string) $merchantItem['image']['thumbnail_url']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function photoPath(Shop $shop, MerchantLocalProduct $localProduct): string
    {
        return \sprintf(
            '/api/merchant/stores/%s/local-products/%s/photo',
            $shop->getId(),
            $localProduct->getId(),
        );
    }

    private function uploadPhoto(Shop $shop, MerchantLocalProduct $localProduct, string $binary, User $user): Response
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_TEST_USER' => $user->getEmail()];
        $request = Request::create(
            $this->photoPath($shop, $localProduct),
            'POST',
            files: ['photo' => $this->makeUpload($binary, 'photo.jpg', 'image/jpeg')],
            server: $server,
        );

        return self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
    }

    private function makeUpload(string $binary, string $name, string $mime): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mpimg');
        self::assertIsString($tmp);
        file_put_contents($tmp, $binary);

        return new UploadedFile($tmp, $name, $mime, null, true);
    }

    /**
     * @return array{0: MerchantLocalProduct, 1: MerchantProduct}
     */
    private function createLocalProduct(Shop $shop, string $nameFr): array
    {
        $localProduct = (new MerchantLocalProduct())
            ->setShop($shop)
            ->setNameFr($nameFr)
            ->setUnit(ProductUnit::Piece);

        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setLocalProduct($localProduct)
            ->setPriceTnd('4.500')
            ->setVisible(true)
            ->setAvailable(true);

        $this->entityManager->persist($localProduct);
        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return [$localProduct, $merchantProduct];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private function findCatalogItem(array $items, ?string $merchantProductId): array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $merchantProductId) {
                return $item;
            }
        }

        self::fail(\sprintf('Merchant product %s not found in catalog payload.', (string) $merchantProductId));
    }

    private function storedOriginalFile(ProductImage $image): string
    {
        $uploadDir = (string) self::getContainer()->getParameter('app.product_image.upload_dir');
        $fileName = basename($image->getOriginalPath());

        return $uploadDir.'/'.$image->getStorageKey().'/'.$fileName;
    }

    private function productImageRepository(): ProductImageRepository
    {
        return self::getContainer()->get(ProductImageRepository::class);
    }

    private function jpegBinary(int $width, int $height): string
    {
        return $this->encode($width, $height, 'jpeg');
    }

    private function pngBinary(int $width, int $height): string
    {
        return $this->encode($width, $height, 'png');
    }

    private function gifBinary(int $width, int $height): string
    {
        return $this->encode($width, $height, 'gif');
    }

    /**
     * A real JPEG with a minimal EXIF APP1 segment injected after SOI — the
     * fixture used to prove the stored original no longer carries metadata.
     */
    private function jpegWithExif(int $width, int $height): string
    {
        $jpeg = $this->jpegBinary($width, $height);

        // Minimal valid TIFF payload: little-endian header + one empty IFD.
        $tiff = 'II'."\x2A\x00\x08\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        $exifPayload = "Exif\x00\x00".$tiff;
        $app1 = "\xFF\xE1".pack('n', \strlen($exifPayload) + 2).$exifPayload;

        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }

    private function encode(int $width, int $height, string $format): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, imagecolorallocate($image, 90, 140, 60));
        ob_start();
        match ($format) {
            'png' => imagepng($image),
            'gif' => imagegif($image),
            default => imagejpeg($image),
        };
        $data = (string) ob_get_clean();
        imagedestroy($image);

        return $data;
    }
}
