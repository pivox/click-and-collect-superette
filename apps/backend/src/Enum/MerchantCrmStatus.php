<?php

declare(strict_types=1);

namespace App\Enum;

enum MerchantCrmStatus: string
{
    case Prospect = 'prospect';
    case Client = 'client';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
