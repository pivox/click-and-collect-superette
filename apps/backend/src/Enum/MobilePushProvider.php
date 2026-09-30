<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * MOBILE-PUSH #620: Expo Push Service first (ADR-0007). The field exists so a
 * future direct FCM/APNs adapter can be routed per device without touching
 * the business code (docs/mobile/push-and-links.md §4).
 */
enum MobilePushProvider: string
{
    case Expo = 'expo';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $provider): string => $provider->value, self::cases());
    }
}
