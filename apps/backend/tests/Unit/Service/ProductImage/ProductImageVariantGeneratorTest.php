<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProductImage;

use App\Exception\InvalidProductImageException;
use App\Service\ProductImage\ProductImageVariantGenerator;
use PHPUnit\Framework\TestCase;

final class ProductImageVariantGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\function_exists('imagewebp') || !\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD with WebP support is required for the image pipeline.');
        }
    }

    public function testGeneratesWebpVariantsAndJpegFallback(): void
    {
        $generator = new ProductImageVariantGenerator(400);

        $result = $generator->generate($this->pngBinary(500, 500));

        self::assertSame(500, $result->width);
        self::assertSame(500, $result->height);
        self::assertSame('image/png', $result->mimeType);

        foreach (ProductImageVariantGenerator::VARIANT_SIZES as $size) {
            self::assertArrayHasKey($size, $result->webpVariants);
            $info = getimagesizefromstring($result->webpVariants[$size]);
            self::assertNotFalse($info, \sprintf('Variant %d should be a valid image', $size));
            self::assertSame('image/webp', $info['mime']);
            // Never upscaled beyond the original, capped at the target size.
            self::assertLessThanOrEqual(min($size, 500), max($info[0], $info[1]));
        }

        $fallbackInfo = getimagesizefromstring($result->jpegFallback);
        self::assertNotFalse($fallbackInfo);
        self::assertSame('image/jpeg', $fallbackInfo['mime']);
    }

    public function testNeverUpscalesSmallerThanTargetOriginal(): void
    {
        $generator = new ProductImageVariantGenerator(400);

        $result = $generator->generate($this->pngBinary(450, 450));

        // 1200 target but original is 450 → stays 450, no upscaling.
        $info = getimagesizefromstring($result->webpVariants[1200]);
        self::assertNotFalse($info);
        self::assertSame(450, $info[0]);
    }

    public function testRejectsImageBelowMinimumDimensions(): void
    {
        $generator = new ProductImageVariantGenerator(400);

        $this->expectException(InvalidProductImageException::class);
        $this->expectExceptionMessageMatches('/'.InvalidProductImageException::ERROR_TOO_SMALL.'/');

        $generator->generate($this->pngBinary(300, 300));
    }

    public function testRejectsUnsupportedMime(): void
    {
        if (!\function_exists('imagegif')) {
            self::markTestSkipped('GD GIF support required.');
        }

        $generator = new ProductImageVariantGenerator(400);

        $this->expectException(InvalidProductImageException::class);
        $this->expectExceptionMessageMatches('/'.InvalidProductImageException::ERROR_UNSUPPORTED_MIME.'/');

        $generator->generate($this->gifBinary(500, 500));
    }

    public function testRejectsUnreadableBytes(): void
    {
        $generator = new ProductImageVariantGenerator(400);

        $this->expectException(InvalidProductImageException::class);
        $this->expectExceptionMessageMatches('/'.InvalidProductImageException::ERROR_UNREADABLE.'/');

        $generator->generate('this is definitely not an image');
    }

    private function pngBinary(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 180, 90));
        ob_start();
        imagepng($image);
        $data = (string) ob_get_clean();
        imagedestroy($image);

        return $data;
    }

    private function gifBinary(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, imagecolorallocate($image, 10, 20, 30));
        ob_start();
        imagegif($image);
        $data = (string) ob_get_clean();
        imagedestroy($image);

        return $data;
    }
}
