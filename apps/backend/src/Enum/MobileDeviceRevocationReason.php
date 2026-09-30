<?php

declare(strict_types=1);

namespace App\Enum;

enum MobileDeviceRevocationReason: string
{
    case Logout = 'logout';
    case ProviderRejected = 'provider_rejected';
    case AccountDeleted = 'account_deleted';
    case Inactive = 'inactive';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $reason): string => $reason->value, self::cases());
    }
}
