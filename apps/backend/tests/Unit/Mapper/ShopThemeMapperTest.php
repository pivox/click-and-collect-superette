<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mapper;

use App\Dto\ThemeWriteInput;
use App\Entity\PlatformTheme;
use App\Entity\ShopTheme;
use App\Enum\ThemeFontFamily;
use App\Mapper\ShopThemeMapper;
use App\Service\ThemeContrastChecker;
use PHPUnit\Framework\TestCase;

final class ShopThemeMapperTest extends TestCase
{
    public function testItMapsShopThemeToMerchantOutput(): void
    {
        $theme = (new ShopTheme())
            ->setPrimaryColor('#111111')
            ->setSecondaryColor('#222222')
            ->setAccentColor('#333333')
            ->setTextColor('#FFFFFF')
            ->setBackgroundColor('#FFFFFF')
            ->setFontFamily(ThemeFontFamily::Cairo)
            ->setBaseFontSize(18);

        $output = $this->mapper()->toMerchantOutput($theme, 'store-id');

        self::assertSame('#111111', $output->primaryColor);
        self::assertSame('#222222', $output->secondaryColor);
        self::assertSame('#333333', $output->accentColor);
        self::assertSame('#FFFFFF', $output->textColor);
        self::assertSame('#FFFFFF', $output->backgroundColor);
        self::assertSame('cairo', $output->fontFamily);
        self::assertSame(18, $output->baseFontSize);
        self::assertSame('store-id', $output->storeId);
        self::assertCount(1, $output->warnings);
    }

    public function testItMapsInheritedPlatformThemeToMerchantOutput(): void
    {
        $theme = (new PlatformTheme())
            ->setPrimaryColor('#123456')
            ->setFontFamily(ThemeFontFamily::Roboto);

        $output = $this->mapper()->toMerchantOutput($theme, 'store-id');

        self::assertSame('#123456', $output->primaryColor);
        self::assertSame('roboto', $output->fontFamily);
        self::assertSame('store-id', $output->storeId);
    }

    public function testItAppliesWriteInputToShopTheme(): void
    {
        $theme = new ShopTheme();
        $input = new ThemeWriteInput(
            primaryColor: '#123456',
            secondaryColor: '#234567',
            accentColor: '#345678',
            textColor: '#456789',
            backgroundColor: '#56789A',
            fontFamily: 'noto_sans_arabic',
            baseFontSize: 20,
        );

        $this->mapper()->applyWriteInput($theme, $input);

        self::assertSame('#123456', $theme->getPrimaryColor());
        self::assertSame('#234567', $theme->getSecondaryColor());
        self::assertSame('#345678', $theme->getAccentColor());
        self::assertSame('#456789', $theme->getTextColor());
        self::assertSame('#56789A', $theme->getBackgroundColor());
        self::assertSame(ThemeFontFamily::NotoSansArabic, $theme->getFontFamily());
        self::assertSame(20, $theme->getBaseFontSize());
    }

    private function mapper(): ShopThemeMapper
    {
        return new ShopThemeMapper(new ThemeContrastChecker());
    }
}
