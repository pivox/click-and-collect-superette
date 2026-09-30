<?php

declare(strict_types=1);

namespace App\Service\Push;

/**
 * MOBILE-PUSH #620: classification of a provider response.
 *
 * - temporary failure → retriable (Messenger retry_strategy);
 * - permanent failure → never retried; `deviceUnregistered` additionally
 *   marks the responses that must revoke the device (DeviceNotRegistered —
 *   decision 6), unlike e.g. MessageTooBig which is permanent but does not
 *   invalidate the token.
 */
final readonly class PushSendResult
{
    private function __construct(
        public PushSendStatus $status,
        public ?string $reason = null,
        public bool $deviceUnregistered = false,
    ) {
    }

    public static function success(): self
    {
        return new self(PushSendStatus::Success);
    }

    public static function temporaryFailure(string $reason): self
    {
        return new self(PushSendStatus::TemporaryFailure, $reason);
    }

    public static function permanentFailure(string $reason, bool $deviceUnregistered = false): self
    {
        return new self(PushSendStatus::PermanentFailure, $reason, $deviceUnregistered);
    }

    public function isSuccess(): bool
    {
        return PushSendStatus::Success === $this->status;
    }

    public function isTemporaryFailure(): bool
    {
        return PushSendStatus::TemporaryFailure === $this->status;
    }

    public function isPermanentFailure(): bool
    {
        return PushSendStatus::PermanentFailure === $this->status;
    }
}
