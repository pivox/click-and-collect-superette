<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\QrCodePngGenerator;
use PHPUnit\Framework\TestCase;

final class QrCodePngGeneratorTest extends TestCase
{
    public function testFunctionPatternsReserveFormatInformationModulesBeforeDataPlacement(): void
    {
        $generator = new QrCodePngGenerator();

        $this->callPrivate($generator, 'initializeMatrix');
        $this->callPrivate($generator, 'drawFunctionPatterns');

        $reserved = $this->readPrivate($generator, 'reserved');
        self::assertIsArray($reserved);

        foreach ($this->formatCoordinates() as [$x, $y]) {
            self::assertTrue($reserved[$y][$x], \sprintf('Format module %d,%d must be reserved before data placement.', $x, $y));
        }
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function formatCoordinates(): array
    {
        $size = 37;

        return [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
            [$size - 1, 8], [$size - 2, 8], [$size - 3, 8], [$size - 4, 8],
            [$size - 5, 8], [$size - 6, 8], [$size - 7, 8], [$size - 8, 8],
            [8, $size - 7], [8, $size - 6], [8, $size - 5], [8, $size - 4],
            [8, $size - 3], [8, $size - 2], [8, $size - 1],
        ];
    }

    private function callPrivate(QrCodePngGenerator $generator, string $method): void
    {
        $reflectionMethod = new \ReflectionMethod($generator, $method);
        $reflectionMethod->invoke($generator);
    }

    private function readPrivate(QrCodePngGenerator $generator, string $property): mixed
    {
        $reflectionProperty = new \ReflectionProperty($generator, $property);

        return $reflectionProperty->getValue($generator);
    }
}
