<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Raised by the product image pipeline when an upload cannot be accepted or
 * processed. The message carries a stable error code consumed by the controller
 * to build a clear 4xx response (see AdminProductReferenceImageController).
 */
final class InvalidProductImageException extends \RuntimeException
{
    public const ERROR_FILE_REQUIRED = 'PRODUCT_IMAGE_FILE_REQUIRED';
    public const ERROR_UNREADABLE = 'PRODUCT_IMAGE_UNREADABLE';
    public const ERROR_UNSUPPORTED_MIME = 'PRODUCT_IMAGE_UNSUPPORTED_MIME';
    public const ERROR_TOO_LARGE = 'PRODUCT_IMAGE_TOO_LARGE';
    public const ERROR_TOO_SMALL = 'PRODUCT_IMAGE_TOO_SMALL';
    public const ERROR_VARIANT_GENERATION_FAILED = 'PRODUCT_IMAGE_VARIANT_GENERATION_FAILED';

    public static function fileRequired(): self
    {
        return new self(self::ERROR_FILE_REQUIRED);
    }

    public static function unreadable(): self
    {
        return new self(self::ERROR_UNREADABLE);
    }

    public static function unsupportedMime(string $mime): self
    {
        return new self(self::ERROR_UNSUPPORTED_MIME.': '.$mime);
    }

    public static function tooLarge(): self
    {
        return new self(self::ERROR_TOO_LARGE);
    }

    public static function tooSmall(int $minSize): self
    {
        return new self(\sprintf('%s: min %dx%d', self::ERROR_TOO_SMALL, $minSize, $minSize));
    }

    public static function variantGenerationFailed(string $detail = ''): self
    {
        return new self(rtrim(self::ERROR_VARIANT_GENERATION_FAILED.': '.$detail, ': '));
    }

    /**
     * The stable error code prefix, used to map the exception to an HTTP response
     * without leaking the variable suffix (mime type, dimensions...).
     */
    public function errorCode(): string
    {
        $message = $this->getMessage();
        $colon = strpos($message, ':');

        return false === $colon ? $message : substr($message, 0, $colon);
    }
}
