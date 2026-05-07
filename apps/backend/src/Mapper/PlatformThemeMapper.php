<?php

declare(strict_types=1);

namespace App\Mapper;

use App\ApiResource\PlatformThemeOutput;
use App\Dto\ThemeWriteInput;
use App\Entity\PlatformTheme;
use App\Enum\ThemeFontFamily;
use App\Service\ThemeContrastChecker;

final readonly class PlatformThemeMapper
{
    public function __construct(
        private ThemeContrastChecker $themeContrastChecker,
    ) {
    }

    public function toOutput(PlatformTheme $platformTheme): PlatformThemeOutput
    {
        return new PlatformThemeOutput(
            primaryColor: $platformTheme->getPrimaryColor(),
            secondaryColor: $platformTheme->getSecondaryColor(),
            accentColor: $platformTheme->getAccentColor(),
            textColor: $platformTheme->getTextColor(),
            backgroundColor: $platformTheme->getBackgroundColor(),
            fontFamily: $platformTheme->getFontFamily()->value,
            baseFontSize: $platformTheme->getBaseFontSize(),
            warnings: $this->themeContrastChecker->buildWarnings(
                $platformTheme->getTextColor(),
                $platformTheme->getBackgroundColor(),
            ),
        );
    }

    public function applyWriteInput(PlatformTheme $platformTheme, ThemeWriteInput $input): PlatformTheme
    {
        return $platformTheme
            ->setPrimaryColor($input->primaryColor)
            ->setSecondaryColor($input->secondaryColor)
            ->setAccentColor($input->accentColor)
            ->setTextColor($input->textColor)
            ->setBackgroundColor($input->backgroundColor)
            ->setFontFamily(ThemeFontFamily::from($input->fontFamily))
            ->setBaseFontSize($input->baseFontSize);
    }
}
