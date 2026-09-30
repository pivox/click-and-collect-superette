<?php

declare(strict_types=1);

namespace App\Message;

/**
 * MOBILE-PUSH #620: one message per (notification, device) — the payload only
 * carries opaque identifiers; the async handler reloads both rows and skips
 * silently when either has disappeared or been revoked. Per-device messages
 * keep retries isolated: a temporary failure on one device never re-sends to
 * the others, and the provider collapse id (= notification id) absorbs any
 * duplicate delivery.
 */
final readonly class SendMobilePushMessage
{
    public function __construct(
        public string $notificationId,
        public string $deviceId,
    ) {
    }
}
