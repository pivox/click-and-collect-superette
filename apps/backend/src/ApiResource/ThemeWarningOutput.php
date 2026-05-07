<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class ThemeWarningOutput
{
    public function __construct(
        #[Groups(['admin_theme:read'])]
        public string $code,
        #[Groups(['admin_theme:read'])]
        public string $message,
        #[Groups(['admin_theme:read'])]
        #[SerializedName('contrast_ratio')]
        public float $contrastRatio,
    ) {
    }
}
