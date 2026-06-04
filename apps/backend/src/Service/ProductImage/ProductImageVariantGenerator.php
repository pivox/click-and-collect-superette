<?php

declare(strict_types=1);

namespace App\Service\ProductImage;

use App\Exception\InvalidProductImageException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Transforms a raw uploaded image into responsive WebP variants + a JPEG fallback,
 * using the GD extension (no external service / binary).
 *
 * Validation performed here (content-based, never extension-based):
 * - the bytes must decode to a real image (getimagesizefromstring + imagecreatefromstring);
 * - the MIME must be JPEG, PNG or WebP;
 * - the source must be at least {minDimension}x{minDimension} px.
 *
 * Variants preserve the aspect ratio and never upscale beyond the original; the
 * frontend renders them inside a 1:1 box (object-contain), which matches the
 * recommended square ratio without distorting non-square sources.
 */
final readonly class ProductImageVariantGenerator
{
    /** @var list<int> Target widths in px (longest side capped at this value). */
    public const VARIANT_SIZES = [200, 400, 800, 1200];

    /** Size used for the JPEG fallback (fiche produit baseline). */
    public const FALLBACK_SIZE = 800;

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const WEBP_QUALITY = 82;
    private const JPEG_QUALITY = 85;

    public function __construct(
        #[Autowire(param: 'app.product_image.min_dimension')]
        private int $minDimension = 400,
    ) {
    }

    public function generate(string $contents): GeneratedProductImage
    {
        if (!\function_exists('imagecreatefromstring') || !\function_exists('imagewebp')) {
            throw InvalidProductImageException::variantGenerationFailed('gd_webp_unavailable');
        }

        $info = @getimagesizefromstring($contents);
        if (false === $info) {
            throw InvalidProductImageException::unreadable();
        }

        $mime = $info['mime'];
        if (!\in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw InvalidProductImageException::unsupportedMime($mime);
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width < $this->minDimension || $height < $this->minDimension) {
            throw InvalidProductImageException::tooSmall($this->minDimension);
        }

        $source = @imagecreatefromstring($contents);
        if (false === $source) {
            throw InvalidProductImageException::unreadable();
        }

        try {
            $webpVariants = [];
            foreach (self::VARIANT_SIZES as $size) {
                $resized = $this->resizeWithin($source, $width, $height, $size, withAlpha: true);
                $webpVariants[$size] = $this->encodeWebp($resized);
                imagedestroy($resized);
            }

            $fallback = $this->resizeWithin($source, $width, $height, self::FALLBACK_SIZE, withAlpha: false);
            $jpeg = $this->encodeJpeg($fallback);
            imagedestroy($fallback);
        } finally {
            imagedestroy($source);
        }

        return new GeneratedProductImage(
            width: $width,
            height: $height,
            mimeType: $mime,
            originalExtension: $this->extensionForMime($mime),
            originalContents: $contents,
            webpVariants: $webpVariants,
            jpegFallback: $jpeg,
        );
    }

    /**
     * Scale the source so its longest side fits within $target, preserving aspect
     * ratio and never upscaling. Transparency is preserved for WebP and flattened
     * onto white for the JPEG fallback.
     */
    private function resizeWithin(\GdImage $source, int $width, int $height, int $target, bool $withAlpha): \GdImage
    {
        $scale = min(1.0, $target / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        if (false === $canvas) {
            throw InvalidProductImageException::variantGenerationFailed('canvas');
        }

        if ($withAlpha) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            if (false !== $transparent) {
                imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
            }
        } else {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            if (false !== $white) {
                imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $white);
            }
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    private function encodeWebp(\GdImage $image): string
    {
        ob_start();
        $ok = imagewebp($image, null, self::WEBP_QUALITY);
        $data = ob_get_clean();
        if (!$ok || false === $data || '' === $data) {
            throw InvalidProductImageException::variantGenerationFailed('webp');
        }

        return $data;
    }

    private function encodeJpeg(\GdImage $image): string
    {
        ob_start();
        $ok = imagejpeg($image, null, self::JPEG_QUALITY);
        $data = ob_get_clean();
        if (!$ok || false === $data || '' === $data) {
            throw InvalidProductImageException::variantGenerationFailed('jpeg');
        }

        return $data;
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
