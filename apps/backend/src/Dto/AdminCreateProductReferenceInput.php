<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminCreateProductReferenceInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $nameFr = null;

    #[Assert\Length(max: 255)]
    public ?string $nameAr = null;

    #[Assert\Length(max: 160)]
    public ?string $variantFr = null;

    #[Assert\Length(max: 160)]
    public ?string $variantAr = null;

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public ?string $brandId = null;

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public ?string $categoryId = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [ProductUnit::class, 'values'])]
    public ?string $unit = null;

    public ?string $volume = null;

    #[Assert\Length(max: 64)]
    public ?string $barcode = null;

    /** @var list<string>|null */
    public ?array $aliases = null;

    #[Assert\Length(max: 2)]
    public ?string $country = null;

    #[Assert\Choice(callback: [ProductReferenceStatus::class, 'values'])]
    public ?string $status = null;
}
