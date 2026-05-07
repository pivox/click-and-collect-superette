<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mapper;

use App\Dto\ThemeWriteInput;
use App\Entity\PlatformTheme;
use App\Enum\ThemeFontFamily;
use App\Mapper\PlatformThemeMapper;
use App\Service\ThemeContrastChecker;
use PHPUnit\Framework\TestCase;

final class PlatformThemeMapperTest extends TestCase
{
    public function testItMapsPlatformThemeToAdminOutput(): void
    {
        $theme = (new PlatformTheme())
            ->setPrimaryColor('#111111')
            ->setSecondaryColor('#222222')
            ->setAccentColor('#333333')
            ->setTextColor('#FFFFFF')
            ->setBackgroundColor('#FFFFFF')
            ->setFontFamily(ThemeFontFamily::NotoSansArabic)
            ->setBaseFontSize(18);

        $output = $this->mapper()->toOutput($theme);

        self::assertSame('#111111', $output->primaryColor);
        self::assertSame('#222222', $output->secondaryColor);
        self::assertSame('#333333', $output->accentColor);
        self::assertSame('#FFFFFF', $output->textColor);
        self::assertSame('#FFFFFF', $output->backgroundColor);
        self::assertSame('noto_sans_arabic', $output->fontFamily);
        self::assertSame(18, $output->baseFontSize);
        self::assertCount(1, $output->warnings);
    }

    public function testItAppliesWriteInputToPlatformTheme(): void
    {
        $theme = new PlatformTheme();
        $input = new ThemeWriteInput(
            primaryColor: '#123456',
            secondaryColor: '#234567',
            accentColor: '#345678',
            textColor: '#456789',
            backgroundColor: '#56789A',
            fontFamily: 'cairo',
            baseFontSize: 20,
        );

        $this->mapper()->applyWriteInput($theme, $input);

        self::assertSame('#123456', $theme->getPrimaryColor());
        self::assertSame('#234567', $theme->getSecondaryColor());
        self::assertSame('#345678', $theme->getAccentColor());
        self::assertSame('#456789', $theme->getTextColor());
        self::assertSame('#56789A', $theme->getBackgroundColor());
        self::assertSame(ThemeFontFamily::Cairo, $theme->getFontFamily());
        self::assertSame(20, $theme->getBaseFontSize());
    }

    private function mapper(): PlatformThemeMapper
    {
        return new PlatformThemeMapper(new ThemeContrastChecker());
    }
}
