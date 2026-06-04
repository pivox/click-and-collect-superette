<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\ProductImage;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductImageSource;
use App\Enum\ProductImageStatus;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Repository\ProductImageRepository;
use App\Service\ProductImage\ProductImageApplicationService;
use App\Service\ProductImage\ProductImageStoreCommand;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Uid\Uuid;

final class AdminProductReferenceImageApiTest extends FunctionalApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!\function_exists('imagewebp') || !\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD with WebP support is required for the image pipeline.');
        }
    }

    public function testAdminUploadsJpegImageAndItBecomesOfficial(): void
    {
        $admin = $this->createUser('admin-img-jpeg@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Vitalait', 'Lait', 'lait', 'Lait demi-écrémé');

        $response = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->jpegBinary(600, 600), 'photo.jpg', 'image/jpeg'),
            ['alt' => 'Bouteille de lait'],
            $admin,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertArrayHasKey('image', $payload);
        $image = $payload['image'];
        self::assertSame('verified', $image['status']);
        self::assertSame('Bouteille de lait', $image['alt']);
        self::assertStringContainsString('/200.webp', (string) $image['thumbnail_url']);
        self::assertStringContainsString('/400.webp', (string) $image['card_url']);
        self::assertStringContainsString('/800.webp', (string) $image['detail_url']);
        self::assertStringContainsString('/1200.webp', (string) $image['zoom_url']);
        self::assertStringContainsString('/fallback.jpg', (string) $image['fallback_jpeg_url']);
        self::assertNotNull($image['original_url']);

        // Files were physically written under the uploads root.
        $stored = $this->productImageRepository()->findOfficialForProductReference($reference);
        self::assertInstanceOf(ProductImage::class, $stored);
        self::assertSame(ProductImageSource::AdminUpload, $stored->getSource());
        self::assertSame(ProductImageStatus::Verified, $stored->getStatus());
        $this->assertVariantFilesExist($stored);
    }

    public function testAdminUploadsPngImage(): void
    {
        $admin = $this->createUser('admin-img-png@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Délice', 'Yaourts', 'yaourts', 'Yaourt nature');

        $response = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->pngBinary(500, 500), 'photo.png', 'image/png'),
            [],
            $admin,
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('verified', $this->decodeJson($response)['image']['status']);
    }

    public function testAdminUploadsWebpImageWhenSupported(): void
    {
        if (!\function_exists('imagecreatefromwebp')) {
            self::markTestSkipped('GD WebP decoding required.');
        }

        $admin = $this->createUser('admin-img-webp@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Safia', 'Eaux', 'eaux', 'Eau minérale');

        $response = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->webpBinary(500, 500), 'photo.webp', 'image/webp'),
            [],
            $admin,
        );

        self::assertSame(201, $response->getStatusCode());
    }

    public function testNonAdminCannotUpload(): void
    {
        $customer = $this->createUser('customer-img@example.test', ['ROLE_CUSTOMER']);
        $reference = $this->createProductReference('Randa', 'Pâtes', 'pates', 'Spaghetti');

        $response = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->jpegBinary(500, 500), 'photo.jpg', 'image/jpeg'),
            [],
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testMissingFileReturns400(): void
    {
        $admin = $this->createUser('admin-img-nofile@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Jouda', 'Conserves', 'conserves', 'Harissa');

        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_TEST_USER' => $admin->getEmail()];
        $request = Request::create(\sprintf('/api/admin/product-references/%s/image', $reference->getId()), 'POST', server: $server);
        $response = self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);

        self::assertSame(400, $response->getStatusCode());
    }

    public function testUnreadableFileReturns422(): void
    {
        $admin = $this->createUser('admin-img-unreadable@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Candia', 'Boissons', 'boissons', 'Jus');

        $response = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload('not an image at all', 'fake.jpg', 'image/jpeg'),
            [],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testTooSmallImageReturns422(): void
    {
        $admin = $this->createUser('admin-img-small@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Nadhif', 'Entretien', 'entretien', 'Savon');

        $response = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->jpegBinary(200, 200), 'small.jpg', 'image/jpeg'),
            [],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testTooLargeFileReturns422(): void
    {
        $admin = $this->createUser('admin-img-large@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Saïda', 'Biscuits', 'biscuits', 'Biscuits');

        // 2.1 MB of bytes — rejected by the size guard before any decoding.
        $oversized = str_repeat('x', 2_200_000);

        $response = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($oversized, 'big.jpg', 'image/jpeg'),
            [],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUploadForUnknownProductReferenceReturns404(): void
    {
        $admin = $this->createUser('admin-img-404@example.test', ['ROLE_ADMIN']);

        $response = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', Uuid::v4()),
            $this->makeUpload($this->jpegBinary(500, 500), 'photo.jpg', 'image/jpeg'),
            [],
            $admin,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testReplacingImageRemovesPreviousOfficialImage(): void
    {
        $admin = $this->createUser('admin-img-replace@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Vitalait', 'Lait', 'lait', 'Lait entier');

        $first = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->jpegBinary(500, 500), 'a.jpg', 'image/jpeg'),
            [],
            $admin,
        );
        self::assertSame(201, $first->getStatusCode());
        $firstImage = $this->productImageRepository()->findOfficialForProductReference($reference);
        self::assertInstanceOf(ProductImage::class, $firstImage);
        $firstImageId = $firstImage->getId()->toRfc4122();

        $second = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->pngBinary(500, 500), 'b.png', 'image/png'),
            [],
            $admin,
        );
        self::assertSame(201, $second->getStatusCode());

        $this->entityManager->clear();
        $remaining = $this->productImageRepository()->findBy(['productReference' => $reference]);
        self::assertCount(1, $remaining, 'Only one image must remain after replacement.');
        self::assertNotSame($firstImageId, $remaining[0]->getId()->toRfc4122());
    }

    public function testDeleteRemovesOfficialImage(): void
    {
        $admin = $this->createUser('admin-img-delete@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Délice', 'Lait', 'lait-2', 'Lben');

        $upload = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->jpegBinary(500, 500), 'a.jpg', 'image/jpeg'),
            [],
            $admin,
        );
        self::assertSame(201, $upload->getStatusCode());

        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_TEST_USER' => $admin->getEmail()];
        $request = Request::create(\sprintf('/api/admin/product-references/%s/image', $reference->getId()), 'DELETE', server: $server);
        $deleteResponse = self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);

        self::assertSame(204, $deleteResponse->getStatusCode());
        $this->entityManager->clear();
        self::assertNull($this->productImageRepository()->findOfficialForProductReference($reference));
    }

    public function testPublicCatalogExposesImageWhenPresentAndOmitsItOtherwise(): void
    {
        $admin = $this->createUser('admin-img-catalog@example.test', ['ROLE_ADMIN']);
        $shop = $this->createShop();

        $withImage = $this->createProductReference('Vitalait', 'Lait', 'lait', 'Lait avec photo');
        $withoutImage = $this->createProductReference('Délice', 'Yaourts', 'yaourts', 'Yaourt sans photo');
        $this->createMerchantProduct($shop, $withImage);
        $this->createMerchantProduct($shop, $withoutImage);

        $upload = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $withImage->getId()),
            $this->makeUpload($this->jpegBinary(600, 600), 'photo.jpg', 'image/jpeg'),
            ['alt' => 'Lait'],
            $admin,
        );
        self::assertSame(201, $upload->getStatusCode());

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shop->getId()));
        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        $byName = [];
        foreach ($payload['items'] as $item) {
            $byName[$item['name_fr']] = $item;
        }

        self::assertArrayHasKey('image', $byName['Lait avec photo']);
        self::assertSame('verified', $byName['Lait avec photo']['image']['status']);
        self::assertStringContainsString('/400.webp', (string) $byName['Lait avec photo']['image']['card_url']);

        // No image → the nested object is simply absent (never blocks the product).
        self::assertArrayNotHasKey('image', $byName['Yaourt sans photo']);
    }

    public function testAdminDetailExposesImageAfterUpload(): void
    {
        $admin = $this->createUser('admin-img-detail@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Vitalait', 'Lait', 'lait', 'Lait détail');

        $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->jpegBinary(500, 500), 'photo.jpg', 'image/jpeg'),
            [],
            $admin,
        );

        $response = $this->requestJson('GET', \sprintf('/api/admin/product-references/%s', $reference->getId()), user: $admin);
        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertArrayHasKey('image', $payload);
        self::assertSame('verified', $payload['image']['status']);
    }

    public function testAiSourceNeverProducesVerifiedImage(): void
    {
        $reference = $this->createProductReference('Vitalait', 'Lait', 'lait', 'Lait IA');

        /** @var ProductImageApplicationService $service */
        $service = self::getContainer()->get(ProductImageApplicationService::class);

        // Even when explicitly asked for Verified, an AI source is downgraded.
        $image = $service->store(new ProductImageStoreCommand(
            contents: $this->jpegBinary(500, 500),
            source: ProductImageSource::AiEnrichment,
            productReference: $reference,
            statusOverride: ProductImageStatus::Verified,
        ));

        self::assertSame(ProductImageStatus::NeedsReview, $image->getStatus());
        self::assertSame(ProductImageSource::AiEnrichment, $image->getSource());

        // It is therefore NOT the official image of the reference.
        self::assertNull($this->productImageRepository()->findOfficialForProductReference($reference));
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * @param array<string, string> $params
     */
    private function upload(string $path, UploadedFile $file, array $params, User $user): Response
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_TEST_USER' => $user->getEmail()];
        $request = Request::create($path, 'POST', parameters: $params, files: ['image' => $file], server: $server);

        return self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
    }

    private function makeUpload(string $binary, string $name, string $mime): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pimg');
        self::assertIsString($tmp);
        file_put_contents($tmp, $binary);

        // test: true → isValid()/getSize() behave like a real upload in the test env.
        return new UploadedFile($tmp, $name, $mime, null, true);
    }

    private function assertVariantFilesExist(ProductImage $image): void
    {
        $uploadDir = (string) self::getContainer()->getParameter('app.product_image.upload_dir');
        $base = $uploadDir.'/'.$image->getStorageKey();
        foreach (['200.webp', '400.webp', '800.webp', '1200.webp', 'fallback.jpg'] as $file) {
            self::assertFileExists($base.'/'.$file);
        }
    }

    private function productImageRepository(): ProductImageRepository
    {
        return self::getContainer()->get(ProductImageRepository::class);
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

    private function createMerchantProduct(Shop $shop, ProductReference $productReference): MerchantProduct
    {
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('1.650')
            ->setVisible(true)
            ->setAvailable(true);

        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }

    private function jpegBinary(int $width, int $height): string
    {
        return $this->encode($width, $height, 'jpeg');
    }

    private function pngBinary(int $width, int $height): string
    {
        return $this->encode($width, $height, 'png');
    }

    private function webpBinary(int $width, int $height): string
    {
        return $this->encode($width, $height, 'webp');
    }

    private function encode(int $width, int $height, string $format): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 60));
        ob_start();
        match ($format) {
            'png' => imagepng($image),
            'webp' => imagewebp($image),
            default => imagejpeg($image),
        };
        $data = (string) ob_get_clean();
        imagedestroy($image);

        return $data;
    }
}
