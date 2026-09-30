<?php

declare(strict_types=1);

namespace App\Enum;

enum MobilePlatform: string
{
    case Android = 'android';
    case Ios = 'ios';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $platform): string => $platform->value, self::cases());
    }
}
