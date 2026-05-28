<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AdminMergeProductProposalInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        #[SerializedName('product_reference_id')]
        public string $productReferenceId = '',
    ) {
    }
}
