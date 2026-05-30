<?php

declare(strict_types=1);

namespace App\Service;

final class PickupSlotDisplayTime
{
    private const TIMEZONE = 'Africa/Tunis';

    public static function toLocalAtom(\DateTimeImmutable $dateTime): string
    {
        return self::fromStoredLocalClock($dateTime)->format(\DateTimeInterface::ATOM);
    }

    public static function fromPayloadInstant(\DateTimeImmutable $dateTime): \DateTimeImmutable
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);
        $localInstant = $dateTime->setTimezone($timezone);

        return self::fromStoredLocalClock($localInstant);
    }

    public static function fromStoredLocalClock(\DateTimeImmutable $dateTime): \DateTimeImmutable
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);

        return (\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTime->format('Y-m-d H:i:s'), $timezone) ?: $dateTime)
            ->setTimezone($timezone);
    }
}
