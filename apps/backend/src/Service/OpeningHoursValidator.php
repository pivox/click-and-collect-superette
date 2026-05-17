<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\OpeningHoursValidationException;

final class OpeningHoursValidator
{
    private const TIMEZONE = 'Africa/Tunis';
    private const MAX_RANGES_PER_DAY = 2;

    /**
     * @param array<string, mixed>|null $openingHours
     *
     * @return array<string, mixed>
     */
    public function validateAndNormalize(?array $openingHours): array
    {
        if (null === $openingHours) {
            throw $this->validationError('OPENING_HOURS_REQUIRED');
        }

        if (!\array_key_exists('timezone', $openingHours)) {
            throw $this->validationError('OPENING_HOURS_TIMEZONE_REQUIRED');
        }

        if (self::TIMEZONE !== $openingHours['timezone']) {
            throw $this->validationError('OPENING_HOURS_TIMEZONE_INVALID');
        }

        if (!\array_key_exists('weekly', $openingHours) || !\is_array($openingHours['weekly'])) {
            throw $this->validationError('OPENING_HOURS_WEEKLY_REQUIRED');
        }

        $weekly = $openingHours['weekly'];
        $expectedDays = array_map('strval', range(1, 7));
        $actualDays = array_map('strval', array_keys($weekly));
        sort($actualDays);

        if ($expectedDays !== $actualDays) {
            throw $this->validationError('OPENING_HOURS_WEEKLY_DAYS_INVALID');
        }

        $normalizedWeekly = [];
        foreach ($expectedDays as $day) {
            $normalizedWeekly[$day] = $this->validateDayRanges($weekly[$day]);
        }

        return [
            'timezone' => self::TIMEZONE,
            'weekly' => $normalizedWeekly,
        ];
    }

    /**
     * @return list<array{start: string, end: string}>
     */
    private function validateDayRanges(mixed $ranges): array
    {
        if (!\is_array($ranges) || !array_is_list($ranges)) {
            throw $this->validationError('OPENING_HOURS_DAY_RANGES_INVALID');
        }

        if (\count($ranges) > self::MAX_RANGES_PER_DAY) {
            throw $this->validationError('OPENING_HOURS_TOO_MANY_RANGES');
        }

        $normalized = [];
        foreach ($ranges as $range) {
            if (!\is_array($range)) {
                throw $this->validationError('OPENING_HOURS_RANGE_INVALID');
            }

            $keys = array_keys($range);
            sort($keys);
            if (['end', 'start'] !== $keys) {
                throw $this->validationError('OPENING_HOURS_RANGE_INVALID');
            }

            $start = $this->validateTime($range['start'], 'OPENING_HOURS_INVALID_START_TIME');
            $end = $this->validateTime($range['end'], 'OPENING_HOURS_INVALID_END_TIME');

            if ($start >= $end) {
                throw $this->validationError('OPENING_HOURS_START_MUST_BE_BEFORE_END');
            }

            $normalized[] = ['start' => $start, 'end' => $end];
        }

        usort(
            $normalized,
            static fn (array $a, array $b): int => $a['start'] <=> $b['start'],
        );

        return $this->assertNoOverlap($normalized);
    }

    private function validateTime(mixed $value, string $errorCode): string
    {
        if (!\is_string($value) || 1 !== preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            throw $this->validationError($errorCode);
        }

        return $value;
    }

    /**
     * @param list<array{start: string, end: string}> $ranges
     */
    /**
     * @param list<array{start: string, end: string}> $ranges
     *
     * @return list<array{start: string, end: string}>
     */
    private function assertNoOverlap(array $ranges): array
    {
        $previousEnd = null;
        foreach ($ranges as $range) {
            if (null !== $previousEnd && $range['start'] < $previousEnd) {
                throw $this->validationError('OPENING_HOURS_RANGES_OVERLAP');
            }

            $previousEnd = $range['end'];
        }

        return $ranges;
    }

    private function validationError(string $message): OpeningHoursValidationException
    {
        return new OpeningHoursValidationException($message);
    }
}
