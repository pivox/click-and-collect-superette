<?php

declare(strict_types=1);

namespace App\Service\ProductImage;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Persists the original + generated variants of a product image to local storage,
 * grouped under a single per-image directory (the image UUID). Returns the relative
 * public web paths so the pipeline can store them on the entity.
 *
 * Files live under a controlled uploads root (public/uploads/products by default);
 * the public path prefix is configurable so a future CDN / object storage can be
 * swapped in without touching the pipeline.
 */
final readonly class ProductImageStorage
{
    private Filesystem $filesystem;

    public function __construct(
        #[Autowire(param: 'app.product_image.upload_dir')]
        private string $uploadDir,
        #[Autowire(param: 'app.product_image.public_path_prefix')]
        private string $publicPathPrefix,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Write the original and every variant under uploads/{storageKey}/, replacing
     * any previous content for that key.
     */
    public function store(string $storageKey, GeneratedProductImage $generated): StoredProductImagePaths
    {
        $directory = $this->directoryFor($storageKey);
        // Start from a clean directory so a replacement never leaves stale files.
        $this->filesystem->remove($directory);
        $this->filesystem->mkdir($directory, 0o755);

        $originalName = 'original.'.$generated->originalExtension;
        $this->filesystem->dumpFile($directory.'/'.$originalName, $generated->originalContents);

        $variants = [];
        foreach ($generated->webpVariants as $size => $binary) {
            $fileName = $size.'.webp';
            $this->filesystem->dumpFile($directory.'/'.$fileName, $binary);
            $variants[(string) $size] = $this->publicPath($storageKey, $fileName);
        }

        $this->filesystem->dumpFile($directory.'/fallback.jpg', $generated->jpegFallback);
        $variants['fallback_jpeg'] = $this->publicPath($storageKey, 'fallback.jpg');

        return new StoredProductImagePaths(
            originalPath: $this->publicPath($storageKey, $originalName),
            variants: $variants,
        );
    }

    /**
     * Remove every file of a stored image (used when an image is replaced or deleted).
     */
    public function remove(string $storageKey): void
    {
        $directory = $this->directoryFor($storageKey);
        if ($this->filesystem->exists($directory)) {
            $this->filesystem->remove($directory);
        }
    }

    private function directoryFor(string $storageKey): string
    {
        return rtrim($this->uploadDir, '/').'/'.$storageKey;
    }

    private function publicPath(string $storageKey, string $fileName): string
    {
        return rtrim($this->publicPathPrefix, '/').'/'.$storageKey.'/'.$fileName;
    }
}
