<?php

declare(strict_types=1);

namespace App\Service\ProductImage;

/**
 * In-memory result of the image transformation step: the preserved original plus
 * the binary contents of every generated WebP variant and the JPEG fallback.
 * Storage is a separate concern (ProductImageStorage).
 */
final readonly class GeneratedProductImage
{
    /**
     * @param array<int, string> $webpVariants size (px) => WebP binary contents
     * @param string             $jpegFallback JPEG fallback binary contents
     */
    public function __construct(
        public int $width,
        public int $height,
        public string $mimeType,
        public string $originalExtension,
        public string $originalContents,
        public array $webpVariants,
        public string $jpegFallback,
    ) {
    }
}
