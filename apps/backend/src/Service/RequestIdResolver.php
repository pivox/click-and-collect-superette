<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Uid\Uuid;

/**
 * Resolves the effective correlation request id for an API request (#617).
 *
 * A client-provided id (X-Client-Request-Id header) is kept only when it matches
 * a bounded safe format: 8 to 64 characters within [A-Za-z0-9-] (UUIDs included).
 * Any absent, malformed or oversized value is replaced by a server-generated UUID v4,
 * so an arbitrary unbounded value is never reflected back to clients or logs.
 */
final readonly class RequestIdResolver
{
    private const CLIENT_ID_PATTERN = '/^[A-Za-z0-9-]{8,64}$/';

    public function resolve(?string $clientProvidedId): string
    {
        if (null !== $clientProvidedId && 1 === preg_match(self::CLIENT_ID_PATTERN, $clientProvidedId)) {
            return $clientProvidedId;
        }

        return Uuid::v4()->toRfc4122();
    }
}
