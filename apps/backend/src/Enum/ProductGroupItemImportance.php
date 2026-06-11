<?php

declare(strict_types=1);

namespace App\Enum;

enum ProductGroupItemImportance: string
{
    case Required = 'required';
    case Recommended = 'recommended';
    case Optional = 'optional';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $importance): string => $importance->value,
            self::cases(),
        );
    }
}
