<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ThemeContrastChecker;
use PHPUnit\Framework\TestCase;

final class ThemeContrastCheckerTest extends TestCase
{
    public function testItProducesAWarningWhenContrastIsTooLow(): void
    {
        $warnings = (new ThemeContrastChecker())->buildWarnings('#777777', '#888888');

        self::assertCount(1, $warnings);
        self::assertSame('low_text_background_contrast', $warnings[0]->code);
        self::assertLessThan(4.5, $warnings[0]->contrastRatio);
    }

    public function testItDoesNotProduceWarningsWhenContrastIsAccessible(): void
    {
        $warnings = (new ThemeContrastChecker())->buildWarnings('#111111', '#FFFFFF');

        self::assertSame([], $warnings);
    }
}
