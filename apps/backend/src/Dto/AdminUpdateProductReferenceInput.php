<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ProductUnit;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminUpdateProductReferenceInput
{
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 255)]
    public ?string $nameFr = null;

    #[Assert\Length(max: 255)]
    public ?string $nameAr = null;

    #[Assert\Length(max: 160)]
    public ?string $variantFr = null;

    #[Assert\Length(max: 160)]
    public ?string $variantAr = null;

    #[Assert\Uuid]
    public ?string $brandId = null;

    #[Assert\Uuid]
    public ?string $categoryId = null;

    #[Assert\Choice(callback: [ProductUnit::class, 'values'])]
    public ?string $unit = null;

    public ?string $volume = null;

    #[Assert\Length(max: 64)]
    public ?string $barcode = null;

    /** @var list<string>|null */
    public ?array $aliases = null;

    #[Assert\Length(max: 2)]
    public ?string $country = null;

    #[Assert\Choice(choices: ['draft', 'pending_review', 'approved', 'rejected'])]
    public ?string $status = null;
}
