<?php

declare(strict_types=1);

namespace App\Monolog;

use Monolog\LogRecord;

/**
 * Injects the X-Client-Request-Id header value into every Monolog record's extra context.
 * The correlation ID is set per-request by CorrelationIdSubscriber.
 */
final class CorrelationIdProcessor
{
    private ?string $correlationId = null;

    public function setCorrelationId(?string $id): void
    {
        $this->correlationId = $id;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if (null !== $this->correlationId) {
            return $record->with(extra: array_merge(
                $record->extra,
                ['correlation_id' => $this->correlationId],
            ));
        }

        return $record;
    }
}
