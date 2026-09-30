<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input of PATCH /api/admin/product-images/{productImageId}/promote
 * (PRODUCT-IMAGE-003): the referential product the merchant photo is promoted to.
 */
final class AdminProductImagePromoteInput
{
    #[Assert\NotBlank(message: 'PRODUCT_IMAGE_PROMOTE_REFERENCE_REQUIRED')]
    #[Assert\Uuid(message: 'PRODUCT_IMAGE_PROMOTE_REFERENCE_INVALID')]
    #[SerializedName('product_reference_id')]
    public ?string $productReferenceId = null;
}
