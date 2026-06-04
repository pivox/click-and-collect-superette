<?php

declare(strict_types=1);

namespace App\Service\ProductImage;

use App\Entity\ProductReference;
use App\Entity\ProductReferenceProposal;
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
     * @param string                        $contents         raw image bytes
     * @param ProductImageSource            $source           where the image comes from
     * @param ProductReference|null         $productReference target referential product (official picture lives here)
     * @param ProductReferenceProposal|null $proposal         target proposal (candidate enrichment / contribution)
     * @param string|null                   $altText          accessible alternative text
     * @param ProductImageStatus|null       $statusOverride   optional explicit status (never allowed to force Verified
     *                                                        for a non-admin source — guarded by the service)
     */
    public function __construct(
        public string $contents,
        public ProductImageSource $source,
        public ?ProductReference $productReference = null,
        public ?ProductReferenceProposal $proposal = null,
        public ?string $altText = null,
        public ?ProductImageStatus $statusOverride = null,
    ) {
    }

    public static function adminUpload(ProductReference $productReference, string $contents, ?string $altText = null): self
    {
        return new self(
            contents: $contents,
            source: ProductImageSource::AdminUpload,
            productReference: $productReference,
            altText: $altText,
        );
    }
}
