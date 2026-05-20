<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminApproveProductProposalInput
{
    #[Assert\Uuid]
    #[SerializedName('productReferenceId')]
    public ?string $productReferenceId = null;

    #[Assert\Valid]
    #[SerializedName('canonicalData')]
    public ?AdminApproveCanonicalData $canonicalData = null;
}
