<?php

declare(strict_types=1);

namespace App\Service\ProductImage;

use App\Entity\MerchantLocalProduct;
use App\Entity\ProductReference;
use App\Entity\ProductReferenceProposal;
use App\Enum\ProductImageLicenseCode;
use App\Enum\ProductImageSource;
use App\Enum\ProductImageStatus;

/**
 * Input of the shared image pipeline (ProductImageApplicationService).
 *
 * The same command shape is used by every caller — admin upload controller, AI
 * enrichment applier, future merchant contributions — so there is a single image
 * pipeline regardless of the origin.
 */
final readonly class ProductImageStoreCommand
{
    /**
     * @param string                        $contents              raw image bytes
     * @param ProductImageSource            $source                where the image comes from
     * @param ProductReference|null         $productReference      target referential product (official picture lives here)
     * @param ProductReferenceProposal|null $proposal              target proposal (candidate enrichment / contribution)
     * @param string|null                   $altText               accessible alternative text
     * @param ProductImageStatus|null       $statusOverride        optional explicit status (never allowed to force Verified
     *                                                             for a non-admin source — guarded by the service)
     * @param ProductImageLicenseCode|null  $licenseCode           usage rights; null → resolved by the service
     *                                                             (platform_owned for an admin upload, unknown otherwise)
     * @param string|null                   $sourceName            human-readable origin (photographer, site, partner…)
     * @param string|null                   $sourceUrl             URL the image was collected from
     * @param string|null                   $attributionText       attribution to display (CC BY / CC BY-SA)
     * @param string|null                   $permissionReference   reference of the written authorization
     * @param \DateTimeImmutable|null       $capturedAt            when the picture was taken, if known
     * @param MerchantLocalProduct|null     $merchantLocalProduct  target local/vrac/reconditioned product
     *                                                             (PRODUCT-IMAGE-003 merchant photo)
     * @param bool                          $stripOriginalMetadata re-encode the stored original through GD so
     *                                                             every metadata block (EXIF/GPS…) is dropped
     * @param int|null                      $minDimension          per-call minimum width/height in px;
     *                                                             null → pipeline default
     */
    public function __construct(
        public string $contents,
        public ProductImageSource $source,
        public ?ProductReference $productReference = null,
        public ?ProductReferenceProposal $proposal = null,
        public ?string $altText = null,
        public ?ProductImageStatus $statusOverride = null,
        public ?ProductImageLicenseCode $licenseCode = null,
        public ?string $sourceName = null,
        public ?string $sourceUrl = null,
        public ?string $attributionText = null,
        public ?string $permissionReference = null,
        public ?\DateTimeImmutable $capturedAt = null,
        public ?MerchantLocalProduct $merchantLocalProduct = null,
        public bool $stripOriginalMetadata = false,
        public ?int $minDimension = null,
    ) {
    }

    public static function adminUpload(
        ProductReference $productReference,
        string $contents,
        ?string $altText = null,
        ?ProductImageLicenseCode $licenseCode = null,
        ?string $sourceName = null,
        ?string $sourceUrl = null,
        ?string $attributionText = null,
        ?string $permissionReference = null,
        ?\DateTimeImmutable $capturedAt = null,
    ): self {
        return new self(
            contents: $contents,
            source: ProductImageSource::AdminUpload,
            productReference: $productReference,
            altText: $altText,
            licenseCode: $licenseCode,
            sourceName: $sourceName,
            sourceUrl: $sourceUrl,
            attributionText: $attributionText,
            permissionReference: $permissionReference,
            capturedAt: $capturedAt,
        );
    }

    /**
     * Merchant photo for a local/vrac/reconditioned product (PRODUCT-IMAGE-003).
     *
     * Lead decisions baked in here:
     * - source merchant_contribution + status Candidate: the photo is the shop's
     *   own picture, exposable only inside its shop catalog, never the official
     *   referential image (promotion stays an explicit admin act);
     * - license merchant_authorized: uploading through the merchant interface is
     *   the merchant's authorization to display the photo on its own catalog;
     * - the stored original is re-encoded (stripOriginalMetadata) so EXIF/GPS
     *   metadata from the merchant's phone never reaches the public uploads dir.
     */
    public static function merchantPhoto(
        MerchantLocalProduct $localProduct,
        string $contents,
        ?string $altText = null,
        ?string $sourceName = null,
        ?\DateTimeImmutable $capturedAt = null,
        ?int $minDimension = null,
    ): self {
        return new self(
            contents: $contents,
            source: ProductImageSource::MerchantContribution,
            altText: $altText,
            statusOverride: ProductImageStatus::Candidate,
            licenseCode: ProductImageLicenseCode::MerchantAuthorized,
            sourceName: $sourceName,
            capturedAt: $capturedAt,
            merchantLocalProduct: $localProduct,
            stripOriginalMetadata: true,
            minDimension: $minDimension,
        );
    }
}
