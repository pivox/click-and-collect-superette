<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Exception\OpeningHoursValidationException;
use App\Service\OpeningHoursValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpeningHoursValidatorTest extends TestCase
{
    public function testItAcceptsAndSortsValidOpeningHours(): void
    {
        $openingHours = $this->validOpeningHours([
            '1' => [
                ['start' => '15:00', 'end' => '20:00'],
                ['start' => '08:00', 'end' => '12:00'],
            ],
        ]);

        $normalized = (new OpeningHoursValidator())->validateAndNormalize($openingHours);

        self::assertSame('Africa/Tunis', $normalized['timezone']);
        self::assertSame([
            ['start' => '08:00', 'end' => '12:00'],
            ['start' => '15:00', 'end' => '20:00'],
        ], $normalized['weekly']['1']);
    }

    public function testItAllowsAdjacentRanges(): void
    {
        $openingHours = $this->validOpeningHours([
            '1' => [
                ['start' => '08:00', 'end' => '12:00'],
                ['start' => '12:00', 'end' => '16:00'],
            ],
        ]);

        $normalized = (new OpeningHoursValidator())->validateAndNormalize($openingHours);

        self::assertSame($openingHours, $normalized);
    }

    /**
     * @param array<string, mixed>|null $openingHours
     */
    #[DataProvider('invalidOpeningHoursProvider')]
    public function testItRejectsInvalidOpeningHours(?array $openingHours, string $expectedMessage): void
    {
        $this->expectException(OpeningHoursValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new OpeningHoursValidator())->validateAndNormalize($openingHours);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>|null, 1: string}>
     */
    public static function invalidOpeningHoursProvider(): iterable
    {
        $valid = self::validOpeningHoursStatic();

        yield 'null payload' => [null, 'OPENING_HOURS_REQUIRED'];
        yield 'missing timezone' => [self::withoutKey($valid, 'timezone'), 'OPENING_HOURS_TIMEZONE_REQUIRED'];
        yield 'invalid timezone' => [self::validOpeningHoursStatic(timezone: 'Europe/Paris'), 'OPENING_HOURS_TIMEZONE_INVALID'];
        yield 'missing weekly' => [self::withoutKey($valid, 'weekly'), 'OPENING_HOURS_WEEKLY_REQUIRED'];
        yield 'missing weekday' => [self::validOpeningHoursStatic(['7' => null]), 'OPENING_HOURS_WEEKLY_DAYS_INVALID'];
        yield 'invalid weekday' => [self::validOpeningHoursStatic(['8' => []]), 'OPENING_HOURS_WEEKLY_DAYS_INVALID'];
        yield 'invalid time' => [self::validOpeningHoursStatic(['1' => [['start' => '8:00', 'end' => '12:00']]]), 'OPENING_HOURS_INVALID_START_TIME'];
        yield 'start equals end' => [self::validOpeningHoursStatic(['1' => [['start' => '12:00', 'end' => '12:00']]]), 'OPENING_HOURS_START_MUST_BE_BEFORE_END'];
        yield 'overlap' => [self::validOpeningHoursStatic(['1' => [['start' => '08:00', 'end' => '12:00'], ['start' => '11:00', 'end' => '14:00']]]), 'OPENING_HOURS_RANGES_OVERLAP'];
        yield 'more than two ranges' => [self::validOpeningHoursStatic(['1' => [
            ['start' => '08:00', 'end' => '09:00'],
            ['start' => '10:00', 'end' => '11:00'],
            ['start' => '12:00', 'end' => '13:00'],
        ]]), 'OPENING_HOURS_TOO_MANY_RANGES'];
    }

    /**
     * @param array<string, list<array{start: string, end: string}>|null> $weeklyOverrides
     *
     * @return array{timezone: string, weekly: array<string, list<array{start: string, end: string}>>}
     */
    private function validOpeningHours(array $weeklyOverrides = [], string $timezone = 'Africa/Tunis'): array
    {
        return self::validOpeningHoursStatic($weeklyOverrides, $timezone);
    }

    /**
     * @param array<string, list<array{start: string, end: string}>|null> $weeklyOverrides
     *
     * @return array{timezone: string, weekly: array<string, list<array{start: string, end: string}>>}
     */
    private static function validOpeningHoursStatic(array $weeklyOverrides = [], string $timezone = 'Africa/Tunis'): array
    {
        $weekly = [
            '1' => [['start' => '08:00', 'end' => '12:00'], ['start' => '15:00', 'end' => '20:00']],
            '2' => [['start' => '08:00', 'end' => '20:00']],
            '3' => [],
            '4' => [],
            '5' => [],
            '6' => [],
            '7' => [],
        ];

        foreach ($weeklyOverrides as $day => $ranges) {
            if (null === $ranges) {
                unset($weekly[$day]);
                continue;
            }

            $weekly[$day] = $ranges;
        }

        return [
            'timezone' => $timezone,
            'weekly' => $weekly,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function withoutKey(array $payload, string $key): array
    {
        unset($payload[$key]);

        return $payload;
    }
}
