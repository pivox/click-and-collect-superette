<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Dto\AdminProductImagePromoteInput;
use App\Dto\AdminProductImageProvenanceInput;
use App\Processor\AdminPromoteProductImageProcessor;
use App\Processor\AdminUpdateProductImageProvenanceProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Admin view of the provenance registry of a stored product image
 * (PRODUCT-IMAGE-004): usage rights, concrete origin, approval trail and
 * replacement history.
 */
#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/admin/product-images/{productImageId<[0-9a-fA-F\-]{32,36}>}/provenance',
            uriVariables: [
                // Pattern #1: the URI variable identifies this output DTO, not the entity.
                'productImageId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: AdminProductImageProvenanceInput::class,
            read: false,
            normalizationContext: ['groups' => ['admin_product_image:read']],
            processor: AdminUpdateProductImageProvenanceProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            validate: true,
        ),
        // PRODUCT-IMAGE-003: promote a merchant local-product photo to the
        // official referential picture (logical duplication — see processor).
        new Patch(
            uriTemplate: '/admin/product-images/{productImageId<[0-9a-fA-F\-]{32,36}>}/promote',
            uriVariables: [
                // Pattern #1: the URI variable identifies this output DTO, not the entity.
                'productImageId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: AdminProductImagePromoteInput::class,
            read: false,
            normalizationContext: ['groups' => ['admin_product_image:read']],
            processor: AdminPromoteProductImageProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            validate: true,
        ),
    ],
)]
final readonly class ProductImageProvenanceOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        public string $id,
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        #[SerializedName('product_reference_id')]
        public ?string $productReferenceId,
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        #[SerializedName('license_code')]
        public string $licenseCode,
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        public string $source,
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        #[SerializedName('source_name')]
        public ?string $sourceName,
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        #[SerializedName('source_url')]
        public ?string $sourceUrl,
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        #[SerializedName('attribution_text')]
        public ?string $attributionText,
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        #[SerializedName('permission_reference')]
        public ?string $permissionReference,
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        #[SerializedName('captured_at')]
        public ?string $capturedAt,
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        #[SerializedName('collected_at')]
        public ?string $collectedAt,
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        #[SerializedName('approved_at')]
        public ?string $approvedAt,
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        #[SerializedName('approved_by_email')]
        public ?string $approvedByEmail,
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        #[SerializedName('superseded_by_id')]
        public ?string $supersededById,
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        public string $status,
        // PRODUCT-IMAGE-003: set when the image is a merchant local-product photo.
        #[Groups(['admin_product_image:read', 'admin_product_image_list:read'])]
        #[SerializedName('merchant_local_product_id')]
        public ?string $merchantLocalProductId = null,
    ) {
    }
}
