<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use ApiPlatform\Metadata\Put;
use App\Dto\ThemeWriteInput;
use App\Entity\PlatformTheme;
use App\Mapper\PlatformThemeMapper;
use App\Processor\UpdatePlatformThemeProcessor;
use App\Repository\PlatformThemeRepository;
use App\Service\ThemeContrastChecker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdatePlatformThemeProcessorTest extends TestCase
{
    public function testItUpdatesTheSeededPlatformTheme(): void
    {
        $theme = new PlatformTheme();
        $repository = $this->platformThemeRepositoryFindingDefault($theme);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('flush');

        $processor = new UpdatePlatformThemeProcessor(
            $repository,
            new PlatformThemeMapper(new ThemeContrastChecker()),
            $entityManager,
        );

        $output = $processor->process(
            new ThemeWriteInput(
                primaryColor: '#123456',
                secondaryColor: '#654321',
                accentColor: '#ABCDEF',
                textColor: '#101010',
                backgroundColor: '#FAFAFA',
                fontFamily: 'roboto',
                baseFontSize: 17,
            ),
            new Put(),
        );

        self::assertSame('#123456', $theme->getPrimaryColor());
        self::assertSame('#654321', $theme->getSecondaryColor());
        self::assertSame('#ABCDEF', $theme->getAccentColor());
        self::assertSame('#101010', $theme->getTextColor());
        self::assertSame('#FAFAFA', $theme->getBackgroundColor());
        self::assertSame('roboto', $output->fontFamily);
        self::assertSame(17, $output->baseFontSize);
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
