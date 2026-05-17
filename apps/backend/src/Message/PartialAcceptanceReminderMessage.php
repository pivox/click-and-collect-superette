<?php

declare(strict_types=1);

namespace App\Message;

final readonly class PartialAcceptanceReminderMessage
{
    public function __construct(
        public string $orderId,
        // Default empty string ensures backward-compatibility: messages already in the async
        // transport before this deploy can still be deserialized. The handler treats '' as a
        // valid (if shared) cycle key, which is acceptable for in-transit legacy messages.
        public string $cycleId = '',
    ) {
    }
}
