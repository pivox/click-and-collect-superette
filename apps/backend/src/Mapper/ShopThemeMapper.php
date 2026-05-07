<?php

declare(strict_types=1);

namespace App\Mapper;

use App\ApiResource\MerchantShopThemeOutput;
use App\Dto\ThemeWriteInput;
use App\Entity\PlatformTheme;
use App\Entity\ShopTheme;
use App\Enum\ThemeFontFamily;
use App\Service\ThemeContrastChecker;

final readonly class ShopThemeMapper
{
    public function __construct(
        private ThemeContrastChecker $themeContrastChecker,
    ) {
    }

    public function toMerchantOutput(PlatformTheme|ShopTheme $theme, string $storeId): MerchantShopThemeOutput
    {
        return new MerchantShopThemeOutput(
            primaryColor: $theme->getPrimaryColor(),
            secondaryColor: $theme->getSecondaryColor(),
            accentColor: $theme->getAccentColor(),
            textColor: $theme->getTextColor(),
            backgroundColor: $theme->getBackgroundColor(),
            fontFamily: $theme->getFontFamily()->value,
            baseFontSize: $theme->getBaseFontSize(),
            warnings: $this->themeContrastChecker->buildWarnings(
                $theme->getTextColor(),
                $theme->getBackgroundColor(),
            ),
            storeId: $storeId,
        );
    }

    public function applyWriteInput(ShopTheme $shopTheme, ThemeWriteInput $input): ShopTheme
    {
        return $shopTheme
            ->setPrimaryColor($input->primaryColor)
            ->setSecondaryColor($input->secondaryColor)
            ->setAccentColor($input->accentColor)
            ->setTextColor($input->textColor)
            ->setBackgroundColor($input->backgroundColor)
            ->setFontFamily(ThemeFontFamily::from($input->fontFamily))
            ->setBaseFontSize($input->baseFontSize);
    }
}
