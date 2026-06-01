<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminRunProductAiEnrichmentInput
{
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 500)]
    public int $limit = 100;
}
