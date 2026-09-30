<?php

declare(strict_types=1);

namespace App\Service\Push;

use App\Enum\MobileApplication;
use App\Service\NotificationService;

/**
 * MOBILE-PUSH #620 lot D: the V1 push events (table §5 of
 * docs/mobile/push-and-links.md). Any notification type outside this catalog
 * emits NO native push — in particular `order_preparing` (in-app only, the
 * "ready" event follows closely) and `merchant_pickup_completed` (the
 * merchant is physically present at handover).
 *
 * Per-occurrence variants (`partial_acceptance_reminder_{cycleId}`,
 * `order_partially_accepted_{uuid}` after a re-submission) are normalized
 * back to their stable base type so the mobile routing table stays closed.
 */
final class MobilePushEventCatalog
{
    /** @var list<string> */
    private const CLIENT_TYPES = [
        NotificationService::TYPE_ORDER_ACCEPTED,
        NotificationService::TYPE_ORDER_PARTIALLY_ACCEPTED,
        NotificationService::TYPE_ORDER_REJECTED,
        NotificationService::TYPE_ORDER_READY,
        NotificationService::TYPE_ORDER_COMPLETED,
        NotificationService::TYPE_PICKUP_REMINDER,
        NotificationService::TYPE_MERCHANT_RESPONSE_TIMEOUT,
        NotificationService::TYPE_PARTIAL_ACCEPTANCE_REMINDER,
        NotificationService::TYPE_PARTIAL_ACCEPTANCE_TIMEOUT,
    ];

    /** @var list<string> */
    private const MERCHANT_TYPES = [
        NotificationService::TYPE_MERCHANT_ORDER_SUBMITTED,
        NotificationService::TYPE_MERCHANT_ORDER_CANCELLED,
    ];

    /**
     * V1 base types that may appear with a per-occurrence variant suffix.
     *
     * @var list<string>
     */
    private const VARIANT_BASE_TYPES = [
        NotificationService::TYPE_PARTIAL_ACCEPTANCE_REMINDER,
        NotificationService::TYPE_ORDER_PARTIALLY_ACCEPTED,
        NotificationService::TYPE_ORDER_ACCEPTED,
        NotificationService::TYPE_ORDER_REJECTED,
        NotificationService::TYPE_ORDER_READY,
        NotificationService::TYPE_ORDER_COMPLETED,
    ];

    private function __construct()
    {
    }

    /**
     * Stable V1 push type for a persisted notification type, or null when the
     * notification is not a V1 push event.
     */
    public static function normalize(?string $notificationType): ?string
    {
        if (null === $notificationType) {
            return null;
        }

        if (\in_array($notificationType, self::CLIENT_TYPES, true)
            || \in_array($notificationType, self::MERCHANT_TYPES, true)) {
            return $notificationType;
        }

        foreach (self::VARIANT_BASE_TYPES as $baseType) {
            // "order_partially_accepted" is checked before "order_accepted"
            // would ever match: prefixes are compared with their underscore
            // separator, so the two bases cannot shadow each other.
            if (str_starts_with($notificationType, $baseType.'_')) {
                return $baseType;
            }
        }

        return null;
    }

    public static function isPushEvent(?string $notificationType): bool
    {
        return null !== self::normalize($notificationType);
    }

    public static function applicationFor(string $basePushType): MobileApplication
    {
        return \in_array($basePushType, self::MERCHANT_TYPES, true)
            ? MobileApplication::Merchant
            : MobileApplication::Client;
    }

    /**
     * Canonical route of the target screen (§7.4): the same path the PWA
     * already serves, reused by the mobile deep-link mapping.
     */
    public static function routeFor(string $basePushType, string $orderId): string
    {
        return MobileApplication::Merchant === self::applicationFor($basePushType)
            ? '/merchant/orders/'.$orderId
            : '/orders/'.$orderId;
    }
}
