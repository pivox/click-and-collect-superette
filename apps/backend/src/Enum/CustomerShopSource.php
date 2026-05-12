<?php

declare(strict_types=1);

namespace App\Enum;

enum CustomerShopSource: string
{
    case QrCode = 'qr_code';
    case Search = 'search';
    case Manual = 'manual';
    case Order = 'order';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $source): string => $source->value,
            self::cases(),
        );
    }
}
