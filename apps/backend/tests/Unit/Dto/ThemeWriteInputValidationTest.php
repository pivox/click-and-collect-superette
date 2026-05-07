<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\ThemeWriteInput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ThemeWriteInputValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testItRejectsInvalidHexColor(): void
    {
        $violations = $this->validator->validate($this->validInput(primaryColor: 'blue'));

        self::assertCount(1, $violations);
        self::assertSame('primaryColor', $violations->get(0)->getPropertyPath());
    }

    public function testItRejectsInvalidFontFamily(): void
    {
        $violations = $this->validator->validate($this->validInput(fontFamily: 'comic_sans'));

        self::assertCount(1, $violations);
        self::assertSame('fontFamily', $violations->get(0)->getPropertyPath());
    }

    /**
     * @dataProvider invalidFontSizes
     */
    public function testItRejectsBaseFontSizeOutsideAllowedRange(int $baseFontSize): void
    {
        $violations = $this->validator->validate($this->validInput(baseFontSize: $baseFontSize));

        self::assertCount(1, $violations);
        self::assertSame('baseFontSize', $violations->get(0)->getPropertyPath());
    }

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function invalidFontSizes(): iterable
    {
        yield 'too small' => [13];
        yield 'too large' => [21];
    }

    private function validInput(
        string $primaryColor = '#1B6CA8',
        string $secondaryColor = '#F0A500',
        string $accentColor = '#E63946',
        string $textColor = '#1A1A1A',
        string $backgroundColor = '#FFFFFF',
        string $fontFamily = 'inter',
        int $baseFontSize = 16,
    ): ThemeWriteInput {
        return new ThemeWriteInput(
            primaryColor: $primaryColor,
            secondaryColor: $secondaryColor,
            accentColor: $accentColor,
            textColor: $textColor,
            backgroundColor: $backgroundColor,
            fontFamily: $fontFamily,
            baseFontSize: $baseFontSize,
        );
    }
}
