<?php

declare(strict_types=1);

namespace App\Enum;

enum MerchantMembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Revoked = 'revoked';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
