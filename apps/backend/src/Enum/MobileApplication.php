<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * MOBILE-PUSH #620: strict client/merchant separation (decision 8 of
 * docs/mobile/push-and-links.md §3) — the application is fixed at device
 * registration and checked against the JWT role.
 */
enum MobileApplication: string
{
    case Client = 'client';
    case Merchant = 'merchant';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $application): string => $application->value, self::cases());
    }
}
