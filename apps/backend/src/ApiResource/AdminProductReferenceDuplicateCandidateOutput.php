<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminProductReferenceDuplicateCandidateOutput
{
    public function __construct(
        #[Groups(['admin_product_reference_duplicates:read'])]
        public AdminProductReferenceOutput $left,
        #[Groups(['admin_product_reference_duplicates:read'])]
        public AdminProductReferenceOutput $right,
        #[Groups(['admin_product_reference_duplicates:read'])]
        public string $reason,
        #[Groups(['admin_product_reference_duplicates:read'])]
        public ?string $barcode,
        #[Groups(['admin_product_reference_duplicates:read'])]
        #[SerializedName('left_offer_count')]
        public int $leftOfferCount,
        #[Groups(['admin_product_reference_duplicates:read'])]
        #[SerializedName('right_offer_count')]
        public int $rightOfferCount,
    ) {
    }
}
