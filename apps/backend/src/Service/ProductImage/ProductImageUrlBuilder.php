<?php

declare(strict_types=1);

namespace App\Service\ProductImage;

use App\ApiResource\ProductImageOutput;
use App\Entity\ProductImage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the responsive URL payload exposed by the API from a stored ProductImage.
 *
 * Stored paths are relative public web paths (e.g. /uploads/products/{id}/400.webp).
 * A configurable base URL (app.product_image.public_base_url) can prefix them so a
 * future CDN / object storage origin can be plugged in without code changes; left
 * empty, the API returns relative paths and the frontend resolves them against the
 * API origin.
 */
final readonly class ProductImageUrlBuilder
{
    public function __construct(
        #[Autowire(param: 'app.product_image.public_base_url')]
        private string $publicBaseUrl = '',
    ) {
    }

    public function build(?ProductImage $image): ?ProductImageOutput
    {
        if (null === $image) {
            return null;
        }

        $variants = $image->getVariants();

        return new ProductImageOutput(
            originalUrl: $this->absolute($image->getOriginalPath()),
            thumbnailUrl: $this->absolute($variants['200'] ?? null),
            cardUrl: $this->absolute($variants['400'] ?? null),
            detailUrl: $this->absolute($variants['800'] ?? null),
            zoomUrl: $this->absolute($variants['1200'] ?? null),
            fallbackJpegUrl: $this->absolute($variants['fallback_jpeg'] ?? null),
            alt: $image->getAltText(),
            status: $image->getStatus()->value,
        );
    }

    /**
     * Snake-case array payload for custom controllers that serialize manually.
     *
     * @return array<string, string|null>|null
     */
    public function buildPayload(?ProductImage $image): ?array
    {
        $output = $this->build($image);
        if (null === $output) {
            return null;
        }

        return [
            'original_url' => $output->originalUrl,
            'thumbnail_url' => $output->thumbnailUrl,
            'card_url' => $output->cardUrl,
            'detail_url' => $output->detailUrl,
            'zoom_url' => $output->zoomUrl,
            'fallback_jpeg_url' => $output->fallbackJpegUrl,
            'alt' => $output->alt,
            'status' => $output->status,
        ];
    }

    private function absolute(?string $relativePath): ?string
    {
        if (null === $relativePath || '' === $relativePath) {
            return null;
        }

        if ('' === $this->publicBaseUrl || 1 === preg_match('#^https?://#i', $relativePath)) {
            return $relativePath;
        }

        return rtrim($this->publicBaseUrl, '/').'/'.ltrim($relativePath, '/');
    }
}
