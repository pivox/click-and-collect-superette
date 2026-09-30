<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\ProductImage;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductImageLicenseCode;
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

/**
 * PRODUCT-IMAGE-004 — provenance, license and usage-rights registry.
 */
final class AdminProductImageProvenanceApiTest extends FunctionalApiTestCase
{
    private const string MISSING_UUID = '550e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        parent::setUp();

        if (!\function_exists('imagewebp') || !\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD with WebP support is required for the image pipeline.');
        }
    }

    // ── Admin upload × license ────────────────────────────────────────────────

    public function testAdminUploadDefaultsToPlatformOwnedLicense(): void
    {
        $admin = $this->createUser('admin-prov-default@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Vitalait', 'Lait', 'lait', 'Lait provenance');

        $response = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->jpegBinary(500, 500), 'a.jpg', 'image/jpeg'),
            [],
            $admin,
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('platform_owned', $this->decodeJson($response)['image']['license_code']);

        $image = $this->productImageRepository()->findOfficialForProductReference($reference);
        self::assertInstanceOf(ProductImage::class, $image);
        self::assertSame(ProductImageLicenseCode::PlatformOwned, $image->getLicenseCode());
        self::assertNotNull($image->getCollectedAt());
    }

    public function testAdminUploadWithExplicitUnknownLicenseIsRefusedWith409(): void
    {
        $admin = $this->createUser('admin-prov-unknown@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Délice', 'Lait', 'lait-u', 'Lait inconnu');

        $response = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->jpegBinary(500, 500), 'a.jpg', 'image/jpeg'),
            ['license_code' => 'unknown'],
            $admin,
        );

        // Chosen behaviour (#584): the admin upload flow only creates official
        // (verified) images, so an explicitly unknown license blocks the creation.
        self::assertSame(409, $response->getStatusCode());
        self::assertCount(0, $this->productImageRepository()->findBy(['productReference' => $reference]));
    }

    public function testAdminUploadWithInvalidLicenseCodeReturns422(): void
    {
        $admin = $this->createUser('admin-prov-badlicense@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Safia', 'Eaux', 'eaux', 'Eau licence');

        $response = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->jpegBinary(500, 500), 'a.jpg', 'image/jpeg'),
            ['license_code' => 'not-a-license'],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testAdminUploadStoresProvenanceFields(): void
    {
        $admin = $this->createUser('admin-prov-fields@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Jouda', 'Conserves', 'conserves', 'Harissa provenance');

        $response = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->jpegBinary(500, 500), 'a.jpg', 'image/jpeg'),
            [
                'license_code' => 'manufacturer_authorized',
                'source_name' => 'Jouda — service marketing',
                'source_url' => 'https://example.test/pack-shots/harissa',
                'permission_reference' => 'EMAIL-2026-09-12-JOUDA',
                'captured_at' => '2026-09-12',
            ],
            $admin,
        );

        self::assertSame(201, $response->getStatusCode());
        $image = $this->productImageRepository()->findOfficialForProductReference($reference);
        self::assertInstanceOf(ProductImage::class, $image);
        self::assertSame(ProductImageLicenseCode::ManufacturerAuthorized, $image->getLicenseCode());
        self::assertSame('Jouda — service marketing', $image->getSourceName());
        self::assertSame('https://example.test/pack-shots/harissa', $image->getSourceUrl());
        self::assertSame('EMAIL-2026-09-12-JOUDA', $image->getPermissionReference());
        self::assertSame('2026-09-12', $image->getCapturedAt()?->format('Y-m-d'));
    }

    // ── PATCH provenance ──────────────────────────────────────────────────────

    public function testPatchProvenanceUpdatesFieldsRecordsApprovalAndAudits(): void
    {
        $admin = $this->createUser('admin-prov-patch@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Saïda', 'Biscuits', 'biscuits', 'Biscuit CC');
        $image = $this->storeCandidateImage($reference);
        self::assertSame(ProductImageLicenseCode::Unknown, $image->getLicenseCode());

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/provenance', $image->getId()->toRfc4122()),
            [
                'license_code' => 'cc_by',
                'source_name' => 'Openverse',
                'source_url' => 'https://example.test/photos/biscuit',
                'attribution_text' => 'Photo: A. Trabelsi (CC BY 4.0)',
                'permission_reference' => 'OPENVERSE-123',
                'captured_at' => '2026-08-01T10:00:00+01:00',
            ],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('cc_by', $payload['license_code']);
        self::assertSame('Openverse', $payload['source_name']);
        self::assertSame('Photo: A. Trabelsi (CC BY 4.0)', $payload['attribution_text']);
        self::assertSame('OPENVERSE-123', $payload['permission_reference']);
        self::assertSame($admin->getEmail(), $payload['approved_by_email']);
        self::assertArrayHasKey('approved_at', $payload);
        self::assertSame('needs_review', $payload['status']);

        $this->entityManager->clear();
        $reloaded = $this->productImageRepository()->find($image->getId());
        self::assertInstanceOf(ProductImage::class, $reloaded);
        self::assertSame(ProductImageLicenseCode::CcBy, $reloaded->getLicenseCode());
        self::assertNotNull($reloaded->getApprovedAt());
        self::assertSame($admin->getEmail(), $reloaded->getApprovedBy()?->getEmail());
        self::assertSame('2026-08-01', $reloaded->getCapturedAt()?->format('Y-m-d'));

        $audit = $this->entityManager->getRepository(AdminAuditLog::class)
            ->findOneBy(['action' => 'product_image.provenance_updated']);
        self::assertInstanceOf(AdminAuditLog::class, $audit);
        self::assertSame('product_image', $audit->getResourceType());
        self::assertSame($image->getId()->toRfc4122(), $audit->getResourceId());
        $metadata = $audit->getMetadata();
        self::assertIsArray($metadata);
        self::assertSame('unknown', $metadata['before']['license_code']);
        self::assertSame('cc_by', $metadata['after']['license_code']);
    }

    public function testPatchProvenanceWithInvalidSourceUrlReturns422(): void
    {
        $admin = $this->createUser('admin-prov-badurl@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Randa', 'Pâtes', 'pates', 'Spaghetti URL');
        $image = $this->storeCandidateImage($reference);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/provenance', $image->getId()->toRfc4122()),
            ['source_url' => 'ftp://not-allowed.test/photo.jpg'],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testPatchProvenanceForUnknownImageReturns404(): void
    {
        $admin = $this->createUser('admin-prov-404@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/provenance', self::MISSING_UUID),
            ['license_code' => 'cc_by'],
            $admin,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPatchProvenanceIsForbiddenForNonAdmin(): void
    {
        $customer = $this->createUser('customer-prov@example.test', ['ROLE_CUSTOMER']);
        $reference = $this->createProductReference('Candia', 'Boissons', 'boissons', 'Jus provenance');
        $image = $this->storeCandidateImage($reference);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/provenance', $image->getId()->toRfc4122()),
            ['license_code' => 'cc_by'],
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testPatchProvenanceCannotDowngradeVerifiedImageToUnknown(): void
    {
        $admin = $this->createUser('admin-prov-downgrade@example.test', ['ROLE_ADMIN']);
        $reference = $this->createProductReference('Vitalait', 'Lait', 'lait-v', 'Lait officiel');

        $upload = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->jpegBinary(500, 500), 'a.jpg', 'image/jpeg'),
            [],
            $admin,
        );
        self::assertSame(201, $upload->getStatusCode());
        $image = $this->productImageRepository()->findOfficialForProductReference($reference);
        self::assertInstanceOf(ProductImage::class, $image);

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/product-images/%s/provenance', $image->getId()->toRfc4122()),
            ['license_code' => 'unknown'],
            $admin,
        );

        self::assertSame(409, $response->getStatusCode());
    }

    // ── Collection + filters ──────────────────────────────────────────────────

    public function testAdminCollectionFiltersImagesWithUnknownLicense(): void
    {
        $admin = $this->createUser('admin-prov-list@example.test', ['ROLE_ADMIN']);
        $referenceA = $this->createProductReference('Vitalait', 'Lait', 'lait-l', 'Lait listé');
        $referenceB = $this->createProductReference('Délice', 'Yaourts', 'yaourts-l', 'Yaourt listé');

        $unknownImage = $this->storeCandidateImage($referenceA);
        $upload = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $referenceB->getId()),
            $this->makeUpload($this->jpegBinary(500, 500), 'b.jpg', 'image/jpeg'),
            [],
            $admin,
        );
        self::assertSame(201, $upload->getStatusCode());

        $response = $this->requestJson('GET', '/api/admin/product-images?license=unknown', user: $admin);
        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertSame(1, $payload['total']);
        self::assertCount(1, $payload['items']);
        self::assertSame($unknownImage->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('unknown', $payload['items'][0]['license_code']);
        self::assertSame($referenceA->getId()->toRfc4122(), $payload['items'][0]['product_reference_id']);

        $all = $this->decodeJson($this->requestJson('GET', '/api/admin/product-images', user: $admin));
        self::assertSame(2, $all['total']);
    }

    public function testAdminCollectionRejectsInvalidFilters(): void
    {
        $admin = $this->createUser('admin-prov-filters@example.test', ['ROLE_ADMIN']);

        $badLicense = $this->requestJson('GET', '/api/admin/product-images?license=gpl', user: $admin);
        self::assertSame(400, $badLicense->getStatusCode());

        $badStatus = $this->requestJson('GET', '/api/admin/product-images?status=published', user: $admin);
        self::assertSame(400, $badStatus->getStatusCode());
    }

    // ── Attribution exposure ──────────────────────────────────────────────────

    public function testCatalogExposesAttributionTextAndLicenseCode(): void
    {
        $admin = $this->createUser('admin-prov-catalog@example.test', ['ROLE_ADMIN']);
        $shop = $this->createShop();
        $reference = $this->createProductReference('Vitalait', 'Lait', 'lait-c', 'Lait attribué');
        $this->createMerchantProduct($shop, $reference);

        $upload = $this->upload(
            \sprintf('/api/admin/product-references/%s/image', $reference->getId()),
            $this->makeUpload($this->jpegBinary(600, 600), 'photo.jpg', 'image/jpeg'),
            [
                'license_code' => 'cc_by',
                'attribution_text' => 'Photo: M. Ben Ali (CC BY 4.0)',
            ],
            $admin,
        );
        self::assertSame(201, $upload->getStatusCode());

        $response = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shop->getId()));
        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        $item = null;
        foreach ($payload['items'] as $candidate) {
            if ('Lait attribué' === $candidate['name_fr']) {
                $item = $candidate;
            }
        }
        self::assertIsArray($item);
        self::assertSame('Photo: M. Ben Ali (CC BY 4.0)', $item['image']['attribution_text']);
        self::assertSame('cc_by', $item['image']['license_code']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Stores a needs_review image with unknown usage rights (open-source flow).
     */
    private function storeCandidateImage(ProductReference $reference): ProductImage
    {
        /** @var ProductImageApplicationService $service */
        $service = self::getContainer()->get(ProductImageApplicationService::class);

        $image = $service->store(new ProductImageStoreCommand(
            contents: $this->jpegBinary(500, 500),
            source: ProductImageSource::OpenSource,
            productReference: $reference,
        ));

        self::assertSame(ProductImageStatus::NeedsReview, $image->getStatus());

        return $image;
    }

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

        return new UploadedFile($tmp, $name, $mime, null, true);
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
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, imagecolorallocate($image, 90, 140, 60));
        ob_start();
        imagejpeg($image);
        $data = (string) ob_get_clean();
        imagedestroy($image);

        return $data;
    }
}
