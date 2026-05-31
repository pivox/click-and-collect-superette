<?php

declare(strict_types=1);

namespace App\Enum;

enum MerchantProductPriceChangeType: string
{
    case Initial = 'initial';
    case ManualUpdate = 'manual_update';
    case ImportUpdate = 'import_update';
    case AdminCorrection = 'admin_correction';
    case SystemUpdate = 'system_update';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $changeType): string => $changeType->value,
            self::cases(),
        );
    }
}
