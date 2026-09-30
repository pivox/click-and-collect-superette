<?php

declare(strict_types=1);

namespace App\Service\Push;

/**
 * MOBILE-PUSH #620: provider-agnostic push message. The data payload is
 * strictly minimal ({type, notification_id, order_id, route} — opaque
 * identifiers only, never Kadhia content, amounts, contact details, notes or
 * tokens — §6 of docs/mobile/push-and-links.md); the collapse id (provider
 * collapse_key / apns-collapse-id) makes retries replace the already
 * displayed notification instead of duplicating it.
 */
final readonly class PushMessage
{
    /**
     * @param array<string, string> $data
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $data,
        public string $collapseId,
    ) {
    }
}
