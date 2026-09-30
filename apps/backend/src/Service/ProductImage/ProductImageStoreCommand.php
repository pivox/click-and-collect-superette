<?php

declare(strict_types=1);

namespace App\Service\ProductImage;

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
     * @param string                        $contents            raw image bytes
     * @param ProductImageSource            $source              where the image comes from
     * @param ProductReference|null         $productReference    target referential product (official picture lives here)
     * @param ProductReferenceProposal|null $proposal            target proposal (candidate enrichment / contribution)
     * @param string|null                   $altText             accessible alternative text
     * @param ProductImageStatus|null       $statusOverride      optional explicit status (never allowed to force Verified
     *                                                           for a non-admin source — guarded by the service)
     * @param ProductImageLicenseCode|null  $licenseCode         usage rights; null → resolved by the service
     *                                                           (platform_owned for an admin upload, unknown otherwise)
     * @param string|null                   $sourceName          human-readable origin (photographer, site, partner…)
     * @param string|null                   $sourceUrl           URL the image was collected from
     * @param string|null                   $attributionText     attribution to display (CC BY / CC BY-SA)
     * @param string|null                   $permissionReference reference of the written authorization
     * @param \DateTimeImmutable|null       $capturedAt          when the picture was taken, if known
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
}
