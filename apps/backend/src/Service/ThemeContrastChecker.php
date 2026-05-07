<?php

declare(strict_types=1);

namespace App\Service;

use App\ApiResource\ThemeWarningOutput;

final class ThemeContrastChecker
{
    private const MINIMUM_AA_CONTRAST_RATIO = 4.5;

    /**
     * @return list<ThemeWarningOutput>
     */
    public function buildWarnings(string $textColor, string $backgroundColor): array
    {
        $contrastRatio = $this->calculateContrastRatio($textColor, $backgroundColor);
        if (null === $contrastRatio || $contrastRatio >= self::MINIMUM_AA_CONTRAST_RATIO) {
            return [];
        }

        return [
            new ThemeWarningOutput(
                code: 'low_text_background_contrast',
                message: 'Text/background contrast is below WCAG AA.',
                contrastRatio: round($contrastRatio, 2),
            ),
        ];
    }

    public function calculateContrastRatio(string $foregroundColor, string $backgroundColor): ?float
    {
        $foregroundRgb = $this->parseHexColor($foregroundColor);
        $backgroundRgb = $this->parseHexColor($backgroundColor);

        if (null === $foregroundRgb || null === $backgroundRgb) {
            return null;
        }

        $foregroundLuminance = $this->relativeLuminance($foregroundRgb);
        $backgroundLuminance = $this->relativeLuminance($backgroundRgb);

        $lighter = max($foregroundLuminance, $backgroundLuminance);
        $darker = min($foregroundLuminance, $backgroundLuminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function parseHexColor(string $hexColor): ?array
    {
        if (1 !== preg_match('/^#[0-9A-Fa-f]{6}$/', $hexColor)) {
            return null;
        }

        return [
            hexdec(substr($hexColor, 1, 2)),
            hexdec(substr($hexColor, 3, 2)),
            hexdec(substr($hexColor, 5, 2)),
        ];
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function relativeLuminance(array $rgb): float
    {
        [$red, $green, $blue] = $rgb;

        return
            0.2126 * $this->linearizeColorChannel($red / 255)
            + 0.7152 * $this->linearizeColorChannel($green / 255)
            + 0.0722 * $this->linearizeColorChannel($blue / 255);
    }

    private function linearizeColorChannel(float $channel): float
    {
        if ($channel <= 0.03928) {
            return $channel / 12.92;
        }

        return (($channel + 0.055) / 1.055) ** 2.4;
    }
}
