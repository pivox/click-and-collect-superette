<?php

declare(strict_types=1);

namespace App\Mapper;

use App\ApiResource\ResolvedThemeOutput;
use App\Entity\PlatformTheme;
use App\Entity\ShopTheme;
use App\Enum\ThemeFontFamily;

final class ThemeCssVariablesMapper
{
    public function map(PlatformTheme|ShopTheme $theme, ?string $storeId = null): ResolvedThemeOutput
    {
        return new ResolvedThemeOutput(
            colorPrimary: $theme->getPrimaryColor(),
            colorSecondary: $theme->getSecondaryColor(),
            colorAccent: $theme->getAccentColor(),
            colorText: $theme->getTextColor(),
            colorBackground: $theme->getBackgroundColor(),
            fontFamily: $this->mapFontFamily($theme->getFontFamily()),
            fontSizeBase: \sprintf('%dpx', $theme->getBaseFontSize()),
            storeId: $storeId,
        );
    }

    private function mapFontFamily(ThemeFontFamily $fontFamily): string
    {
        return match ($fontFamily) {
            ThemeFontFamily::Inter => 'Inter',
            ThemeFontFamily::Cairo => 'Cairo',
            ThemeFontFamily::Roboto => 'Roboto',
            ThemeFontFamily::NotoSansArabic => 'Noto Sans Arabic',
            ThemeFontFamily::System => 'System',
        };
    }
}
