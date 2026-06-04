<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Responsive image representation embedded in product outputs.
 *
 * URLs map to the generated variants:
 * - thumbnail_url : 200px (liste / petite carte)
 * - card_url      : 400px (carte produit standard)
 * - detail_url    : 800px (fiche produit)
 * - zoom_url      : 1200px (zoom admin / HD)
 * - fallback_jpeg_url : JPEG fallback for clients without WebP support
 *
 * When a product has no image the embedding output exposes `image: null`; the
 * frontend then renders the category placeholder.
 */
final readonly class ProductImageOutput
{
    public function __construct(
        #[Groups(['store_catalog:read', 'admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('original_url')]
        public ?string $originalUrl,
        #[Groups(['store_catalog:read', 'admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('thumbnail_url')]
        public ?string $thumbnailUrl,
        #[Groups(['store_catalog:read', 'admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('card_url')]
        public ?string $cardUrl,
        #[Groups(['store_catalog:read', 'admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('detail_url')]
        public ?string $detailUrl,
        #[Groups(['store_catalog:read', 'admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('zoom_url')]
        public ?string $zoomUrl,
        #[Groups(['store_catalog:read', 'admin_product_reference:read', 'admin_product_reference_list:read'])]
        #[SerializedName('fallback_jpeg_url')]
        public ?string $fallbackJpegUrl,
        #[Groups(['store_catalog:read', 'admin_product_reference:read', 'admin_product_reference_list:read'])]
        public ?string $alt,
        #[Groups(['store_catalog:read', 'admin_product_reference:read', 'admin_product_reference_list:read'])]
        public ?string $status,
    ) {
    }
}
