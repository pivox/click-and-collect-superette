<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ProductGroupItemImportance;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminCreateProductGroupItemInput
{
    #[Assert\Uuid]
    public ?string $productReferenceId = null;

    #[Assert\Type('integer')]
    public mixed $sortOrder = null;

    #[Assert\Choice(callback: [ProductGroupItemImportance::class, 'values'])]
    public ?string $importance = null;
}
