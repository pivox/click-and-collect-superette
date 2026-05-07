<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ThemeFontFamily;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ThemeWriteInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/')]
        #[SerializedName('primary_color')]
        public string $primaryColor,
        #[Assert\NotBlank]
        #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/')]
        #[SerializedName('secondary_color')]
        public string $secondaryColor,
        #[Assert\NotBlank]
        #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/')]
        #[SerializedName('accent_color')]
        public string $accentColor,
        #[Assert\NotBlank]
        #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/')]
        #[SerializedName('text_color')]
        public string $textColor,
        #[Assert\NotBlank]
        #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/')]
        #[SerializedName('background_color')]
        public string $backgroundColor,
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [ThemeFontFamily::class, 'values'])]
        #[SerializedName('font_family')]
        public string $fontFamily,
        #[Assert\Range(min: 14, max: 20)]
        #[SerializedName('base_font_size')]
        public int $baseFontSize,
    ) {
    }
}
