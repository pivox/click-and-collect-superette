<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use ApiPlatform\Metadata\Get;
use App\Entity\PlatformTheme;
use App\Mapper\PlatformThemeMapper;
use App\Provider\PlatformThemeProvider;
use App\Repository\PlatformThemeRepository;
use App\Service\ThemeContrastChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PlatformThemeProviderTest extends TestCase
{
    public function testItProvidesTheDefaultPlatformTheme(): void
    {
        $theme = new PlatformTheme();
        $provider = new PlatformThemeProvider(
            $this->platformThemeRepositoryFindingDefault($theme),
            new PlatformThemeMapper(new ThemeContrastChecker()),
        );

        $output = $provider->provide(new Get());

        self::assertSame($theme->getPrimaryColor(), $output->primaryColor);
        self::assertSame($theme->getFontFamily()->value, $output->fontFamily);
    }

    public function testItFailsWhenTheSeededPlatformThemeIsMissing(): void
    {
        $repository = $this->createMock(PlatformThemeRepository::class);
        $repository
            ->expects(self::once())
            ->method('findDefault')
            ->willReturn(null);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('PLATFORM_THEME_UNAVAILABLE');

        (new PlatformThemeProvider(
            $repository,
            new PlatformThemeMapper(new ThemeContrastChecker()),
        ))->provide(new Get());
    }

    /**
     * @return PlatformThemeRepository&MockObject
     */
    private function platformThemeRepositoryFindingDefault(PlatformTheme $platformTheme): PlatformThemeRepository
    {
        $repository = $this->createMock(PlatformThemeRepository::class);
        $repository
            ->expects(self::once())
            ->method('findDefault')
            ->willReturn($platformTheme);

        return $repository;
    }
}
