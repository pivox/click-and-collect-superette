<?php

declare(strict_types=1);

namespace App\Enum;

enum ProductGroupVisibility: string
{
    case AdminOnly = 'admin_only';
    case Merchant = 'merchant';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $visibility): string => $visibility->value,
            self::cases(),
        );
    }
}
