<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mapper;

use App\Entity\PlatformTheme;
use App\Enum\ThemeFontFamily;
use App\Mapper\ThemeCssVariablesMapper;
use PHPUnit\Framework\TestCase;

final class ThemeCssVariablesMapperTest extends TestCase
{
    public function testItMapsThemeToCssVariablesOutput(): void
    {
        $theme = (new PlatformTheme())
            ->setPrimaryColor('#111111')
            ->setSecondaryColor('#222222')
            ->setAccentColor('#333333')
            ->setTextColor('#444444')
            ->setBackgroundColor('#555555')
            ->setFontFamily(ThemeFontFamily::NotoSansArabic)
            ->setBaseFontSize(18);

        $output = (new ThemeCssVariablesMapper())->map($theme);

        self::assertSame('#111111', $output->colorPrimary);
        self::assertSame('#222222', $output->colorSecondary);
        self::assertSame('#333333', $output->colorAccent);
        self::assertSame('#444444', $output->colorText);
        self::assertSame('#555555', $output->colorBackground);
        self::assertSame('Noto Sans Arabic', $output->fontFamily);
        self::assertSame('18px', $output->fontSizeBase);
    }
}
