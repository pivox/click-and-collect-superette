<?php

declare(strict_types=1);

namespace App\Service;

final class PickupSlotDisplayTime
{
    private const TIMEZONE = 'Africa/Tunis';

    public static function toLocalAtom(\DateTimeImmutable $dateTime): string
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);

        return (\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTime->format('Y-m-d H:i:s'), $timezone) ?: $dateTime)
            ->format(\DateTimeInterface::ATOM);
    }
}
