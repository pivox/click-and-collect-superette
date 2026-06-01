<?php

declare(strict_types=1);

namespace App\Service;

final class PickupSlotDuration
{
    public const REQUIRED_DURATION_SECONDS = 3600;

    public static function isExactlyOneHour(\DateTimeInterface $startsAt, \DateTimeInterface $endsAt): bool
    {
        return self::REQUIRED_DURATION_SECONDS === ($endsAt->getTimestamp() - $startsAt->getTimestamp());
    }

    public static function isAtLeastOneHour(\DateTimeInterface $startsAt, \DateTimeInterface $endsAt): bool
    {
        return ($endsAt->getTimestamp() - $startsAt->getTimestamp()) >= self::REQUIRED_DURATION_SECONDS;
    }
}
