<?php

declare(strict_types=1);

namespace App\Service\ProductImage;

/**
 * Relative public web paths produced after persisting an image to storage.
 */
final readonly class StoredProductImagePaths
{
    /**
     * @param array<string, string> $variants map "200"|"400"|"800"|"1200"|"fallback_jpeg" => relative path
     */
    public function __construct(
        public string $originalPath,
        public array $variants,
    ) {
    }
}
