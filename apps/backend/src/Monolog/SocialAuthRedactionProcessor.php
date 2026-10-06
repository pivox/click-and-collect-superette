<?php

declare(strict_types=1);

namespace App\Monolog;

use Monolog\LogRecord;

final class SocialAuthRedactionProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        $uri = $context['request_uri'] ?? null;
        if (\is_string($uri) && str_contains($uri, '/api/auth/social/callback/')) {
            $context['request_uri'] = explode('?', $uri, 2)[0].'?[redacted]';
        }

        return $record->with(context: $context);
    }
}
