<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ProductUnit;
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

final class AdminApproveCanonicalData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[SerializedName('nameFr')]
    public ?string $nameFr = null;

    #[Assert\Length(max: 255)]
    #[SerializedName('nameAr')]
    public ?string $nameAr = null;

    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[SerializedName('brandId')]
    public ?string $brandId = null;

    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[SerializedName('categoryId')]
    public ?string $categoryId = null;

    #[Assert\Length(max: 64)]
    #[SerializedName('barcode')]
    public ?string $barcode = null;

    #[Assert\Choice(callback: [ProductUnit::class, 'values'])]
    #[SerializedName('unit')]
    public ?string $unit = null;

    #[SerializedName('volume')]
    public ?string $volume = null;
}
